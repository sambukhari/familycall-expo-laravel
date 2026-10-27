import React, {useEffect,useRef,useState} from 'react';
import {Alert,Platform,ScrollView,Text,TextInput,TouchableOpacity,View,StyleSheet} from 'react-native';
import Constants from 'expo-constants';
import {mediaDevices,RTCPeerConnection,RTCIceCandidate,RTCSessionDescription,RTCView,MediaStream} from 'react-native-webrtc';

const API = String(Constants.expoConfig?.extra?.apiBaseUrl || 'http://192.168.1.10:8000/api').replace(/\/$/,'');
const ICE={iceServers:[{urls:'stun:stun.l.google.com:19302'}]};
type Call={id:number,caller_id:string,callee_id:string,status:'ringing'|'connected'|'ended',offer:any,answer:any};
const delay=(ms:number)=>new Promise(resolve=>setTimeout(resolve,ms));

export default function App(){
 const [me,setMe]=useState(Platform.isTV?'tv':'phone');
 const [peer,setPeer]=useState(Platform.isTV?'phone':'tv');
 const [secret,setSecret]=useState('replace-with-your-pair-secret');
 const [call,setCall]=useState<Call|null>(null);
 const [status,setStatus]=useState('idle');
 const [local,setLocal]=useState<MediaStream|null>(null);
 const [remote,setRemote]=useState<MediaStream|null>(null);
 const pc=useRef<any>(null);
 const active=useRef<number|null>(null);
 const cursor=useRef(0);
 const answered=useRef(false);
 const current=useRef<Call|null>(null);
 const idRef=useRef(me);
 const secretRef=useRef(secret);
 const pendingIce=useRef<any[]>([]);
 current.current=call;idRef.current=me;secretRef.current=secret;

 async function api(path:string,method='GET',payload?:any){
  const response=await fetch(API+path,{method,headers:{'Content-Type':'application/json','X-Device-ID':idRef.current,'X-Pair-Secret':secretRef.current},body:payload?JSON.stringify(payload):undefined});
  const data=await response.json();
  if(!response.ok)throw new Error(data.message || 'API error '+response.status);
  return data;
 }
 async function reset(){
  active.current=null;cursor.current=0;answered.current=false;pendingIce.current=[];
  try{pc.current?.close();}catch{}
  pc.current=null;
  setLocal(previous=>{previous?.getTracks().forEach(track=>track.stop());return null;});
  setRemote(null);setCall(null);setStatus('idle');
 }
 async function setup(){
  if(pc.current)return pc.current;
  const stream=await mediaDevices.getUserMedia({audio:true,video:{facingMode:'user',width:640,height:480,frameRate:15}});
  setLocal(stream);
  const connection=new RTCPeerConnection(ICE);
  pc.current=connection;
  stream.getTracks().forEach(track=>connection.addTrack(track,stream));
  connection.addEventListener('track',(event:any)=>{if(event.streams?.[0])setRemote(event.streams[0]);});
  connection.addEventListener('icecandidate',(event:any)=>{
   if(event.candidate&&active.current){
    api('/calls/'+active.current+'/signals','POST',{kind:'ice',payload:event.candidate.toJSON()}).catch(console.warn);
   }
  });
  return connection;
 }
 async function drainIce(){
  if(!pc.current?.remoteDescription)return;
  const queued=pendingIce.current.splice(0);
  for(const c of queued){try{await pc.current.addIceCandidate(new RTCIceCandidate(c));}catch(e){console.warn(e);}}
 }
 async function start(){
  try{
   setStatus('calling');
   const connection=await setup();
   const offer=await connection.createOffer();
   await connection.setLocalDescription(offer);
   const result=await api('/calls','POST',{to:peer,offer:{type:offer.type,sdp:offer.sdp}});
   active.current=result.call.id;setCall(result.call);
  }catch(e:any){await reset();Alert.alert('Cannot call',String(e.message||e));}
 }
 async function accept(){
  const c=current.current;if(!c)return;
  try{
   setStatus('connecting');
   active.current=c.id;
   const connection=await setup();
   await connection.setRemoteDescription(new RTCSessionDescription(c.offer));
   await drainIce();
   const answer=await connection.createAnswer();
   await connection.setLocalDescription(answer);
   const response=await api('/calls/'+c.id+'/answer','POST',{answer:{type:answer.type,sdp:answer.sdp}});
   setCall(response.call);setStatus('connected');
  }catch(e:any){Alert.alert('Answer failed',String(e.message||e));}
 }
 async function hangup(){
  try{if(current.current)await api('/calls/'+current.current.id+'/end','POST');}
  catch(e){console.warn(e);}finally{await reset();}
 }
 useEffect(()=>{
  let running=true;
  async function loop(){
   while(running){
    try{
     const result=await api('/calls/active');
     const c=result.call as Call|null;
     if(!c){if(active.current!==null)await reset();}
     else{
      if(active.current!==c.id){
       if(pc.current)await reset();
       active.current=c.id;cursor.current=0;answered.current=false;
      }
      setCall(c);
      if(c.status==='ringing')setStatus(c.caller_id===idRef.current?'calling':'incoming');
      if(c.status==='connected'){
       setStatus('connected');
       if(c.caller_id===idRef.current&&c.answer&&!answered.current&&pc.current){
        await pc.current.setRemoteDescription(new RTCSessionDescription(c.answer));
        answered.current=true;await drainIce();
       }
      }
      const result2=await api('/calls/'+c.id+'/signals?after='+cursor.current);
      for(const sig of result2.signals){
       cursor.current=Math.max(cursor.current,sig.id);
       if(sig.sender_id!==idRef.current&&sig.kind==='ice'){
        if(pc.current?.remoteDescription){
         try{await pc.current.addIceCandidate(new RTCIceCandidate(sig.payload));}catch(e){console.warn(e);}
        }else pendingIce.current.push(sig.payload);
       }
      }
      await drainIce();
     }
    }catch(e){console.warn('Signaling:',e);}
    await delay(1200);
   }
  }
  loop();return()=>{running=false;};
 },[]);
 const btn=(label:string,onPress:()=>void,color='#2563eb')=>(
  <TouchableOpacity accessibilityRole="button" onPress={onPress} style={[styles.button,{backgroundColor:color}]}><Text style={styles.buttonText}>{label}</Text></TouchableOpacity>
 );
 return <ScrollView contentContainerStyle={[styles.root,Platform.isTV&&styles.tv]}>
  <Text style={styles.title}>FamilyCall {Platform.isTV?'TV':'Phone'}</Text>
  <Text style={styles.note}>Call status: {status}</Text>
  <Text style={styles.label}>Device ID</Text>
  <TextInput style={styles.input} value={me} onChangeText={setMe} autoCapitalize="none"/>
  <Text style={styles.label}>Call peer ID</Text>
  <TextInput style={styles.input} value={peer} onChangeText={setPeer} autoCapitalize="none"/>
  <Text style={styles.label}>Shared pairing secret</Text>
  <TextInput style={styles.input} value={secret} onChangeText={setSecret} autoCapitalize="none" secureTextEntry/>
  <View style={styles.videos}>
   {remote?<RTCView streamURL={remote.toURL()} style={styles.video} objectFit="cover"/>:
    <View style={[styles.video,styles.placeholder]}><Text style={styles.note}>Remote video</Text></View>}
   {local&&<RTCView streamURL={local.toURL()} style={styles.preview} objectFit="cover" mirror/>}
  </View>
  {status==='idle'&&btn('Call '+peer,start)}
  {status==='incoming'&&<>{btn('Answer',accept,'#15803d')}{btn('Reject',hangup,'#b91c1c')}</>}
  {status!=='idle'&&status!=='incoming'&&btn('End call',hangup,'#b91c1c')}
  <Text style={styles.note}>Both apps must remain open. TV needs a supported camera and mic. Expo Go is not supported.</Text>
 </ScrollView>;
}
const styles=StyleSheet.create({
 root:{flexGrow:1,backgroundColor:'#0f172a',padding:24,alignItems:'center'},
 tv:{padding:50},title:{fontSize:32,fontWeight:'700',color:'white',marginBottom:12},
 note:{color:'#cbd5e1',fontSize:16,textAlign:'center'},label:{color:'white',marginTop:10},
 input:{backgroundColor:'#fff',width:'100%',maxWidth:520,borderRadius:8,padding:12,marginTop:4,fontSize:16},
 videos:{width:'100%',maxWidth:800,height:330,marginVertical:18,backgroundColor:'#1e293b'},
 video:{width:'100%',height:'100%'},placeholder:{justifyContent:'center',alignItems:'center'},
 preview:{position:'absolute',right:8,bottom:8,width:130,height:100},
 button:{padding:16,borderRadius:10,marginTop:10,minWidth:230,alignItems:'center'},
 buttonText:{fontSize:20,color:'white',fontWeight:'700'}
});
