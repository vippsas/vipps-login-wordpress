(()=>{"use strict";var t,o={672:()=>{const t=window.wp.blocks,o=window.wp.i18n,e=window.wp.components,i=window.wp.blockEditor,a=injectedBlockConfig,l=window.ReactJSXRuntime,n=JSON.parse('{"UU":"login-with-vipps/login-with-vipps-button"}');(0,t.registerBlockType)(n.UU,{title:a.title,icon:(0,l.jsx)("img",{className:"block-editor-block-icon has-colors vipps-smile vipps-component-icon",src:a.iconSrc,alt:a.title+" icon"}),attributes:{application:{default:a.defaultApp},title:{default:a.defaultTitle},preLogo:{default:a.defaultTextPreLogo},postLogo:{default:a.defaultTextPostLogo},loginMethod:{default:a.loginMethod}},edit:function({attributes:t,setAttributes:n}){const s=["core/bold","core/italic"],p=a.applications,r=t.application;let c=!1;for(let t=0;t<p.length;t++)if(r===p[t].value){c=!0;break}c||p.push({label:r,value:r});const u="Vipps"===t.loginMethod?"vipps-background":"mobilepay-background";return(0,l.jsxs)(l.Fragment,{children:[(0,l.jsx)("span",{...(0,i.useBlockProps)({className:"continue-with-vipps-wrapper inline"}),children:(0,l.jsxs)("a",{className:"button vipps-orange vipps-button continue-with-vipps continue-with-vipps-action "+u,title:t.title,"data-application":t,children:[(0,l.jsx)(i.RichText,{className:"prelogo",tagName:"span",allowedFormats:s,value:t.preLogo,onChange:t=>n({preLogo:t})}),(0,l.jsx)("img",{className:"vipps-block-logo-img",alt:t.title,src:a.loginMethodLogoSrc}),(0,l.jsx)(i.RichText,{className:"postlogo",tagName:"span",allowedFormats:s,value:t.postLogo,onChange:t=>n({postLogo:t})})]})}),(0,l.jsx)(i.InspectorControls,{children:(0,l.jsxs)(e.PanelBody,{children:[(0,l.jsx)(e.SelectControl,{onChange:t=>n({application:t}),label:(0,o.__)("Application","login-with-vipps"),value:t.application,options:p,help:a.applicationsText}),(0,l.jsx)(e.TextControl,{onChange:t=>n({title:t}),label:(0,o.__)("Title","login-with-vipps"),value:t.title,help:(0,o.__)("This will be used as the title/popup of the button","login-with-vipps")})]})})]})},save:function({attributes:t}){const o="Vipps"===t.loginMethod?"vipps-background":"mobilepay-background";return(0,l.jsx)(l.Fragment,{children:(0,l.jsx)("span",{...i.useBlockProps.save({className:"continue-with-vipps-wrapper inline"}),children:(0,l.jsxs)("a",{className:"button vipps-orange vipps-button continue-with-vipps continue-with-vipps-action "+o,title:t.title,"data-application":t,href:"javascript: void(0);",children:[(0,l.jsx)(i.RichText.Content,{className:"prelogo",tagName:"span",value:t.preLogo}),(0,l.jsx)("img",{className:"vipps-block-logo-img",alt:t.title,src:a.loginMethodLogoSrc}),(0,l.jsx)(i.RichText.Content,{className:"postlogo",tagName:"span",value:t.postLogo})]})})})}})}},e={};function i(t){var a=e[t];if(void 0!==a)return a.exports;var l=e[t]={exports:{}};return o[t](l,l.exports,i),l.exports}i.m=o,t=[],i.O=(o,e,a,l)=>{if(!e){var n=1/0;for(c=0;c<t.length;c++){e=t[c][0],a=t[c][1],l=t[c][2];for(var s=!0,p=0;p<e.length;p++)(!1&l||n>=l)&&Object.keys(i.O).every((t=>i.O[t](e[p])))?e.splice(p--,1):(s=!1,l<n&&(n=l));if(s){t.splice(c--,1);var r=a();void 0!==r&&(o=r)}}return o}l=l||0;for(var c=t.length;c>0&&t[c-1][2]>l;c--)t[c]=t[c-1];t[c]=[e,a,l]},i.o=(t,o)=>Object.prototype.hasOwnProperty.call(t,o),(()=>{var t={666:0,918:0};i.O.j=o=>0===t[o];var o=(o,e)=>{var a,l,n=e[0],s=e[1],p=e[2],r=0;if(n.some((o=>0!==t[o]))){for(a in s)i.o(s,a)&&(i.m[a]=s[a]);if(p)var c=p(i)}for(o&&o(e);r<n.length;r++)l=n[r],i.o(t,l)&&t[l]&&t[l][0](),t[l]=0;return i.O(c)},e=self.webpackChunk=self.webpackChunk||[];e.forEach(o.bind(null,0)),e.push=o.bind(null,e.push.bind(e))})();var a=i.O(void 0,[918],(()=>i(672)));a=i.O(a)})();