import React, {useEffect, useRef, useState} from 'react';

async function request(path, options = {}) {
  const response = await fetch(path, {
    credentials: 'same-origin',
    headers: {'Accept':'application/json', 'Content-Type':'application/json',
      'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''},
    ...options,
  });
  const result = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(result.message || 'Request failed (' + response.status + ')');
  return result;
}

export default function BrowserCall({accounts, families}) {
  const [target,setTarget] = useState('');
  const [state,setState] = useState('idle');
  const [error,setError] = useState('');
  const localVideo=useRef(null), remoteVideo=useRef(null), pc=useRef(null), stream=useRef(null);
  const id=useRef(null), cursor=useRef(0), pending=useRef([]), remotePending=useRef([]), timer=useRef(null), session=useRef(0);

  async function finish(notify = true) {
    ++session.current;
    clearInterval(timer.current);
    timer.current=null;
    const callId=id.current;
    id.current=null;
    pc.current?.close();
    pc.current=null;
    stream.current?.getTracks().forEach(track=>track.stop());
    stream.current=null;
    if(localVideo.current) localVideo.current.srcObject=null;
    if(remoteVideo.current) remoteVideo.current.srcObject=null;
    pending.current=[]; remotePending.current=[]; cursor.current=0;
    setState('idle');
    if (notify && callId) await request('/admin/browser-calls/'+callId+'/end',{method:'POST',body:'{}'}).catch(()=>{});
  }

  async function start() {
    if (!target || state!=='idle') return;
    const generation=++session.current;
    setError(''); setState('starting');
    try {
      const media=await navigator.mediaDevices.getUserMedia({audio:true,video:true});
      if(generation!==session.current){media.getTracks().forEach(t=>t.stop());return;}
      stream.current=media;
      if(localVideo.current) localVideo.current.srcObject=media;
      const connection=new RTCPeerConnection({iceServers:[{urls:'stun:stun.l.google.com:19302'}]});
      pc.current=connection;
      media.getTracks().forEach(track=>connection.addTrack(track,media));
      connection.ontrack=event=>{if(remoteVideo.current) remoteVideo.current.srcObject=event.streams[0]};
      connection.onicecandidate=event=>{
        if(!event.candidate) return;
        const candidate=event.candidate.toJSON();
        if(!id.current) pending.current.push(candidate);
        else void request('/admin/browser-calls/'+id.current+'/signals',{method:'POST',body:JSON.stringify({kind:'ice',payload:candidate})}).catch(e=>setError(e.message));
      };
      const offer=await connection.createOffer();
      await connection.setLocalDescription(offer);
      const result=await request('/admin/browser-calls',{method:'POST',body:JSON.stringify({to:Number(target),offer:{type:offer.type,sdp:offer.sdp}})});
      if(generation!==session.current){await request('/admin/browser-calls/'+result.call.id+'/end',{method:'POST',body:'{}'});return;}
      id.current=result.call.id;
      for(const payload of pending.current.splice(0)) await request('/admin/browser-calls/'+id.current+'/signals',{method:'POST',body:JSON.stringify({kind:'ice',payload})});
      setState('ringing');
      let polling=false;
      timer.current=setInterval(async()=>{
        if(polling || !id.current) return;
        polling=true;
        try {
          const response=await request('/admin/browser-calls/'+id.current+'?after='+cursor.current);
          if(generation!==session.current)return;
          if(response.call.status==='ended'){await finish(false);return;}
          if(response.call.answer && !connection.remoteDescription){
            await connection.setRemoteDescription(response.call.answer);
            for(const candidate of remotePending.current.splice(0)) await connection.addIceCandidate(candidate);
            setState('connected');
          }
          for(const signal of response.signals){
            cursor.current=Math.max(cursor.current,signal.id);
            if(signal.sender_id===response.call.caller_id || signal.kind!=='ice')continue;
            if(connection.remoteDescription) await connection.addIceCandidate(signal.payload);
            else remotePending.current.push(signal.payload);
          }
        } catch(e){setError(e.message);}
        finally{polling=false;}
      },1500);
    } catch(e){await finish();setError(e.message || 'Unable to start call');}
  }

  useEffect(()=>()=>{void finish();},[]);
  return <div className="stack">
    <label className="field">Family member<select value={target} disabled={state!=='idle'} onChange={e=>setTarget(e.target.value)}>
      <option value="">Choose a member</option>
      {accounts.filter(a=>a.active&&a.family_id).map(a=><option key={a.id} value={a.id}>{a.username} · {families.find(f=>f.id===a.family_id)?.name || 'Family'} · {a.device_type}</option>)}
    </select></label>
    <div className="actions"><button className="btn primary" disabled={!target||state!=='idle'} onClick={()=>void start()}>Call member</button>
    <button className="btn danger" disabled={state==='idle'} onClick={()=>void finish()}>End call</button><span>Call: {state}</span></div>
    {error&&<div className="error">{error}</div>}
    <div className="split"><div><p>Remote video</p><video ref={remoteVideo} autoPlay playsInline style={{width:'100%',background:'#000',minHeight:150}}/></div>
    <div><p>Your camera</p><video ref={localVideo} autoPlay muted playsInline style={{width:'100%',background:'#000',minHeight:150}}/></div></div>
  </div>;
}
