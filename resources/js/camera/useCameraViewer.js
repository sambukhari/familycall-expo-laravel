import {useCallback, useEffect, useRef, useState} from 'react';
import {cameraRequest} from './api';

export function useCameraViewer() {
  const video = useRef(null), active = useRef(null), starting = useRef(false), generation = useRef(0), mounted = useRef(true);
  const [state, setState] = useState('idle'), [error, setError] = useState(''), [label, setLabel] = useState('');
  const update = (value) => {if (mounted.current) setState(value);};

  const stop = useCallback(async (notify = true) => {
    ++generation.current;
    starting.current = false;
    const current = active.current;
    active.current = null;
    if (current) {
      clearTimeout(current.timer); clearTimeout(current.connectTimer); clearTimeout(current.disconnectTimer);
      current.connection.close();
      current.media.getTracks().forEach(track => track.stop());
    }
    if (video.current) video.current.srcObject = null;
    if (mounted.current) setState('idle');
    if (notify && current?.id) await cameraRequest('/sessions/' + current.id + '/end', {}, {keepalive: true}).catch(() => {});
  }, []);

  async function start(device) {
    if (active.current || starting.current) return;
    starting.current = true;
    const g = ++generation.current;
    setError(''); setLabel(device.label); update('starting');
    let current;
    try {
      if (!window.isSecureContext || !window.RTCPeerConnection) throw new Error('Open the admin panel over HTTPS in a browser that supports WebRTC.');
      const config = await cameraRequest('/ice');
      if (g !== generation.current) return;
      const connection = new RTCPeerConnection({iceServers: config.ice_servers});
      current = {connection, media: new MediaStream(), id: null, queue: [], remote: [], cursor: 0, heartbeat: 0, lastPoll: Date.now()};
      active.current = current;
      // Receive only: the administrator's camera and microphone are never requested.
      connection.addTransceiver('video', {direction: 'recvonly'});
      connection.ontrack = ({track}) => {
        if (active.current !== current) return;
        current.media.addTrack(track);
        if (video.current) {video.current.srcObject = current.media; void video.current.play().catch(() => {});}
      };
      connection.onicecandidate = ({candidate}) => {
        if (candidate && active.current === current) current.queue.push({nonce: crypto.randomUUID(), payload: candidate.toJSON()});
      };
      connection.onconnectionstatechange = () => {
        if (active.current !== current) return;
        if (connection.connectionState === 'connected') {
          clearTimeout(current.connectTimer); clearTimeout(current.disconnectTimer); update('live');
        } else if (connection.connectionState === 'failed') {
          void stop(); setError('The video connection failed. Check the TV and TURN service, then try again.');
        } else if (connection.connectionState === 'disconnected') {
          update('reconnecting');
          clearTimeout(current.disconnectTimer);
          current.disconnectTimer = setTimeout(() => {
            if (active.current === current) {void stop(); setError('The TV disconnected.');}
          }, 10000);
        }
      };
      const offer = await connection.createOffer();
      await connection.setLocalDescription(offer);
      if (g !== generation.current) return;
      const result = await cameraRequest('/' + device.id + '/sessions', {offer: {type: offer.type, sdp: offer.sdp}});
      if (g !== generation.current) {
        await cameraRequest('/sessions/' + result.session.id + '/end', {}, {keepalive: true}).catch(() => {});
        return;
      }
      current.id = result.session.id;
      update('connecting');
      current.connectTimer = setTimeout(() => {
        if (active.current === current) {void stop(); setError('The camera did not connect within 35 seconds.');}
      }, 35000);

      async function poll() {
        if (active.current !== current) return;
        try {
          if (Date.now() - current.heartbeat > 8000) {
            await cameraRequest('/sessions/' + current.id + '/heartbeat', {});
            current.heartbeat = Date.now();
          }
          if (active.current !== current) return;
          if (current.queue.length) {
            const candidates = current.queue.slice(0, 20);
            await cameraRequest('/sessions/' + current.id + '/signals', {candidates});
            current.queue.splice(0, candidates.length);
          }
          const result = await cameraRequest('/sessions/' + current.id + '?after=' + current.cursor);
          if (active.current !== current) return;
          current.lastPoll = Date.now();
          if (connection.connectionState === 'connected') update('live');
          if (result.session.status === 'ended') {
            await stop(false);
            setError('Viewing ended: ' + (result.session.end_reason || 'session ended').replaceAll('_', ' ') + '.');
            return;
          }
          if (result.session.answer && !connection.remoteDescription) {
            await connection.setRemoteDescription(result.session.answer);
            for (const candidate of current.remote.splice(0)) await connection.addIceCandidate(candidate);
          }
          for (const signal of result.signals) {
            if (signal.id <= current.cursor) continue;
            if (connection.remoteDescription) await connection.addIceCandidate(signal.payload);
            else current.remote.push(signal.payload);
            current.cursor = signal.id;
          }
        } catch (e) {
          if (active.current !== current) return;
          if ([401, 403, 404, 409, 419, 423].includes(e.status) || Date.now() - current.lastPoll > 10000) {
            await stop(); setError(e.message); return;
          }
          update('reconnecting');
        }
        if (active.current === current) current.timer = setTimeout(poll, 1200);
      }
      void poll();
    } catch (e) {
      if (g === generation.current) {await stop(); if (mounted.current) setError(e.message || 'Unable to start viewing.');}
      else if (current && active.current !== current) current.connection.close();
    }
  }

  useEffect(() => {
    mounted.current = true;
    const leave = () => {void stop();};
    window.addEventListener('pagehide', leave);
    return () => {mounted.current = false; window.removeEventListener('pagehide', leave); void stop();};
  }, [stop]);
  return {video, state, error, label, start, stop};
}
