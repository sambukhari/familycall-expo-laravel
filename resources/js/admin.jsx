import React from 'react';
import {createRoot} from 'react-dom/client';
import {createInertiaApp} from '@inertiajs/react';
createInertiaApp({
  title: title=>title+' · FamilyCall Control',
  resolve: name=>{
    const pages=import.meta.glob('./Pages/*.jsx',{eager:true});
    return pages['./Pages/'+name+'.jsx'].default;
  },
  setup({el,App,props}){createRoot(el).render(<App {...props}/>);},
});
