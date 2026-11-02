import React,{useState} from 'react';
import {Head,router,usePage} from '@inertiajs/react';
export default function Login(){
 const [email,setEmail]=useState(''),[password,setPassword]=useState(''),[loading,setLoading]=useState(false);
 const {errors}=usePage().props;
 const submit=e=>{e.preventDefault();setLoading(true);router.post('/admin/login',{email,password},{onFinish:()=>setLoading(false)});};
 return <main className="login-page"><Head title="Admin sign in"/><section className="login-card"><div className="login-brand">✦ <span>FamilyCall</span></div><p className="eyebrow">PRIVATE CONTROL CENTER</p><h1>Welcome back.</h1><p className="muted">Sign in with your configured administrator account.</p><form onSubmit={submit}><label>Email address<input value={email} onChange={e=>setEmail(e.target.value)} type="email" autoComplete="username" required/></label><label>Password<input type="password" value={password} onChange={e=>setPassword(e.target.value)} autoComplete="current-password" required/></label>{Object.values(errors||{}).map((x,i)=><p className="error" key={i}>{x}</p>)}<button className="btn primary wide" disabled={loading}>{loading?'Signing in…':'Secure sign in →'}</button></form></section></main>;
}
