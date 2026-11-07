import React, {useEffect, useRef, useState} from 'react';
import {Camera, ShieldCheck, Video, Square, Link2, RefreshCw} from 'lucide-react';
import {cameraRequest} from '../camera/api';
import {useCameraViewer} from '../camera/useCameraViewer';

export default function Cameras({accounts = []}) {
  const [data, setData] = useState(null), [error, setError] = useState(''), [working, setWorking] = useState(false);
  const [password, setPassword] = useState(''), [code, setCode] = useState(''), [secret, setSecret] = useState(null);
  const [label, setLabel] = useState('Living room camera'), [account, setAccount] = useState(''), [pairing, setPairing] = useState(null);
  const viewer = useCameraViewer();
  const alive = useRef(true);
  async function refresh() {
    try {const next = await cameraRequest(''); if (alive.current) {setData(next); setError('');}}
    catch (e) {if (alive.current) setError(e.message);}
  }
  useEffect(() => {
    alive.current = true;
    let timer;
    async function poll() {await refresh(); if (alive.current) timer = setTimeout(poll, 5000);}
    void poll();
    return () => {alive.current = false; clearTimeout(timer);};
  }, []);
  async function action(fn) {
    if (working) return;
    setWorking(true); setError('');
    try {await fn(); await refresh();} catch (e) {if (alive.current) setError(e.message);}
    finally {if (alive.current) setWorking(false);}
  }
  const tvs = accounts.filter(a => a.device_type === 'tv');
  return <div className="camera-area">
    <section className="panel camera-intro"><div><div className="eyebrow">PRIVATE CAMERA ACCESS</div><h2>Your room, when you need it.</h2><p className="muted">A separate TV camera companion. Live video is available only while remote access is enabled on the TV.</p></div>{data?.unlocked ? <button className="btn" disabled={working} onClick={() => void action(async () => {await viewer.stop(); await cameraRequest('/security/lock', {}); setPairing(null);})}><ShieldCheck size={18}/> Lock access</button> : <ShieldCheck size={36}/>}</section>
    {error && <div role="alert" className="camera-alert">{error}</div>}
    {!data ? <p className="muted">Checking camera access…</p> : !data.unlocked ? <section className="panel camera-unlock">
      <h2>{data.authenticator_ready ? 'Unlock your cameras' : 'Protect camera access'}</h2>
      <p className="muted">Use your administrator password and a six-digit authenticator code. Camera access unlocks for 30 minutes.</p>
      <form className="stack" onSubmit={e => {e.preventDefault(); void action(async () => {
        if (!data.authenticator_ready && !secret) {
          setSecret(await cameraRequest('/security/enroll', {password}));
        } else {
          await cameraRequest('/security/unlock', {password, code}); setSecret(null); setPassword(''); setCode('');
        }
      });}}>
        <label className="field">Administrator password<input type="password" autoComplete="current-password" value={password} onChange={e => setPassword(e.target.value)} required/></label>
        {secret && <div className="camera-secret"><b>Add an account in your authenticator app</b><p>{secret.issuer} · {secret.account}</p><code>{secret.secret}</code><p>Choose a time-based code. Keep this key private. Setup expires in 10 minutes.</p></div>}
        {(data.authenticator_ready || secret) && <label className="field">Authenticator code<input inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]{6}" maxLength={6} value={code} onChange={e => setCode(e.target.value.replace(/\D/g, ''))} required placeholder="000000"/></label>}
        <button className="btn primary" disabled={working || !password}>{working ? 'Please wait…' : data.authenticator_ready || secret ? 'Unlock camera access' : 'Set up authenticator'}</button>
      </form>
    </section> : <>
      {!data.turn_ready && <div className="camera-alert">Internet viewing is not configured yet. Set CAMERA_TURN_URLS and CAMERA_TURN_SECRET on the backend. You can pair the TV while this is being prepared.</div>}
      <section className="panel camera-viewer">
        <div className="camera-toolbar"><div><h2>{viewer.label || 'Live camera'}</h2><span className={'status ' + (viewer.state === 'live' ? 'ok' : '')}><span className="dot"/>{viewer.state === 'idle' ? 'Ready to view' : viewer.state}</span></div><button className="btn danger" onClick={() => void viewer.stop()} disabled={viewer.state === 'idle'}><Square size={14}/> Stop viewing</button></div>
        <div className="camera-video"><video ref={viewer.video} autoPlay playsInline muted controls={false} aria-label="Live video from your TV camera"/>{viewer.state !== 'live' && <div className="camera-video-placeholder"><Video size={38}/><p>{viewer.state === 'idle' ? 'Choose a camera below' : 'Connecting to your TV…'}</p></div>}</div>
        {viewer.error && <p role="alert" className="camera-alert">{viewer.error}</p>}
        <p className="muted camera-caption">Video only · No recording · Sessions end after 15 minutes · FamilyCall takes priority</p>
      </section>
      <section className="panel"><div className="camera-toolbar"><h2>Paired cameras</h2><button className="btn" onClick={() => void refresh()}><RefreshCw size={14}/> Refresh</button></div>
        <div className="camera-grid">{data.devices.map(device => <article key={device.id} className="camera-card">
          <Camera size={24}/><h3>{device.label}</h3><span className={'status ' + (device.online && device.armed && !device.busy ? 'ok' : '')}>{device.revoked ? 'Access revoked' : !device.paired ? 'Awaiting pairing' : !device.online ? 'Offline' : device.busy ? 'FamilyCall in progress' : !device.armed ? 'Enable on TV' : !device.camera_available ? 'Camera unavailable' : 'Ready'}</span>
          <p className="muted">{device.last_seen_at ? 'Last seen ' + new Date(device.last_seen_at).toLocaleString() : 'Not connected yet'}</p>
          <div className="actions"><button className="btn primary" disabled={viewer.state !== 'idle' || !data.turn_ready || device.revoked || !device.online || !device.armed || !device.camera_available || device.busy} onClick={() => void viewer.start(device)}><Video size={14}/> View camera</button>
            {!device.revoked && <button className="btn danger" disabled={working} onClick={() => {if (window.confirm('Revoke ' + device.label + '? It must be paired again to allow viewing.')) void action(async () => {await cameraRequest('/' + device.id + '/revoke', {}); await viewer.stop();});}}>Revoke</button>}
          </div></article>)}</div>
        {!data.devices.length && <div className="empty">Pair your first TV camera below.</div>}
      </section>
      <section className="panel"><h2>Pair a TV camera</h2><p className="muted">Install FamilyCall Camera on the TV. Link its FamilyCall TV account so calls can take priority, then enter the one-time pairing code in the camera app.</p>
        <form className="stack" onSubmit={e => {e.preventDefault(); void action(async () => setPairing(await cameraRequest('', {label: label.trim(), family_account_id: Number(account)})));}}>
          <div className="split"><label className="field">Camera name<input value={label} onChange={e => setLabel(e.target.value)} maxLength={100} required placeholder="Living room camera"/></label>
            <label className="field">FamilyCall TV account<select required value={account} onChange={e => setAccount(e.target.value)}><option value="">Select your TV</option>{tvs.map(a => <option key={a.id} value={a.id}>{a.username}</option>)}</select></label></div>
          <button className="btn primary" disabled={working || !account || !label.trim()}><Link2 size={14}/> Create pairing code</button>
        </form>
        {pairing && <div className="camera-secret"><b>Enter on your TV</b><p className="camera-pair-code">{pairing.pair_code.match(/.{1,4}/g).join(' ')}</p><p>Valid until {new Date(pairing.expires_at).toLocaleTimeString()}. This code is shown once; keep it private.</p><button className="btn" onClick={() => setPairing(null)}>Hide code</button></div>}
      </section>
    </>}
  </div>;
}
