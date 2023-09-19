!function(e,t){"object"==typeof exports&&"object"==typeof module?module.exports=t():"function"==typeof define&&define.amd?define([],t):"object"==typeof exports?exports.CKEditor5=t():(e.CKEditor5=e.CKEditor5||{},e.CKEditor5.oe_oembed_entities=t())}(self,(()=>(()=>{var e={"ckeditor5/src/core.js":(e,t,i)=>{e.exports=i("dll-reference CKEditor5.dll")("./src/core.js")},"ckeditor5/src/ui.js":(e,t,i)=>{e.exports=i("dll-reference CKEditor5.dll")("./src/ui.js")},"ckeditor5/src/widget.js":(e,t,i)=>{e.exports=i("dll-reference CKEditor5.dll")("./src/widget.js")},"dll-reference CKEditor5.dll":e=>{"use strict";e.exports=CKEditor5.dll}},t={};function i(r){var n=t[r];if(void 0!==n)return n.exports;var o=t[r]={exports:{}};return e[r](o,o.exports,i),o.exports}i.d=(e,t)=>{for(var r in t)i.o(t,r)&&!i.o(e,r)&&Object.defineProperty(e,r,{enumerable:!0,get:t[r]})},i.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t);var r={};return(()=>{"use strict";i.d(r,{default:()=>u});var e=i("ckeditor5/src/core.js"),t=i("ckeditor5/src/widget.js");class n extends e.Command{execute(e){const{model:t}=this.editor,i=this.editor.plugins.get("OembedEntitiesEditing"),r=Object.entries(i.modelAttrs).reduce(((e,[t,i])=>(e[i]=t,e)),{}),n=Object.keys(e).reduce(((t,i)=>(r[i]&&(t[r[i]]=e[i]),t)),{});t.change((e=>{t.insertContent(function(e,t){const{oembedEntitiesEmbedInline:i}=t,r=Boolean(i);return e.createElement(r?"oembedEntityInline":"oembedEntity",t)}(e,n))}))}}class o extends e.Plugin{static get requires(){return[t.Widget]}static get pluginName(){return"OembedEntitiesEditing"}constructor(e){super(e),this.viewAttrs={oembedEntitiesDisplayAs:"data-display-as",oembedEntitiesOembed:"data-oembed"},this.modelAttrs=Object.assign({oembedEntitiesEmbedInline:"data-embed-inline",oembedEntitiesResourceLabel:"data-resource-label",oembedEntitiesResourceUrl:"data-resource-url",oembedEntitiesButtonId:"data-button-id"},this.viewAttrs)}init(){this._defineSchema(),this._defineConverters(),this.editor.commands.add("oembedEntities",new n(this.editor))}_defineSchema(){const e=this.editor.model.schema;e.register("oembedEntity",{isObject:!0,isBlock:!0,allowWhere:"$block",allowAttributes:Object.keys(this.modelAttrs)}),e.register("oembedEntityInline",{isInline:!0,isObject:!0,allowWhere:"$text",allowAttributes:Object.keys(this.modelAttrs)})}_defineConverters(){const{conversion:e}=this.editor;e.for("upcast").add((e=>{e.on("element:p",((e,t,i)=>{const{consumable:r,writer:n,safeInsert:o,updateConversionResult:s}=i,{viewItem:d}=t,a={name:!0},l={name:!0,attributes:"href"};if(!d.hasAttribute("data-oembed"))return;if(!r.test(d,a))return;if(1!==d.childCount)return;const c=d.getChild(0);if(!c.is("element","a"))return;if(!r.test(c,l))return;if(1!==c.childCount)return;const u=c.getChild(0);if(!u.is("text"))return;if(!r.test(u))return;const m=n.createElement("oembedEntity",{oembedEntitiesResourceUrl:c.getAttribute("href"),oembedEntitiesResourceLabel:u.data});o(m,t.modelCursor)&&(r.consume(d,a),r.consume(c,l),r.consume(u),s(m,t))}),{priority:"high"})})),e.for("dataDowncast").elementToStructure({model:"oembedEntity",view:(e,{writer:t})=>this._generateViewBlockElement(e,t,!0)}),e.for("editingDowncast").elementToStructure({model:"oembedEntity",view:(e,{writer:i})=>{const r=this._generateViewBlockElement(e,i);return i.addClass("ck-oe-oembed",r),i.setCustomProperty("OembedEntity",!0,r),(0,t.toWidget)(r,i,{label:Drupal.t("OpenEuropa Oembed widget")})}}),Object.keys(this.viewAttrs).forEach((t=>{const i={model:{key:t,name:"oembedEntity"},view:{name:"p",key:this.viewAttrs[t]}};e.for("dataDowncast").attributeToAttribute(i),e.for("upcast").attributeToAttribute(i)})),e.for("upcast").add((e=>{e.on("element:a",((e,t,i)=>{const{consumable:r,writer:n,safeInsert:o,updateConversionResult:s}=i,{viewItem:d}=t,a={name:!0,attributes:"href"};if(!d.hasAttribute("data-oembed"))return;if(!r.test(d,a))return;if(1!==d.childCount)return;const l=d.getChild(0);if(!l.is("text"))return;if(!r.test(l))return;const c=n.createElement("oembedEntityInline",{oembedEntitiesResourceUrl:d.getAttribute("href"),oembedEntitiesResourceLabel:l.data});o(c,t.modelCursor)&&(r.consume(d,a),r.consume(l),s(c,t))}),{priority:"high"})})),e.for("dataDowncast").elementToElement({model:"oembedEntityInline",view:(e,{writer:t})=>this._generateViewInlineElement(e,t,!0)}),e.for("editingDowncast").elementToElement({model:"oembedEntityInline",view:(e,{writer:i})=>{const r=this._generateViewInlineElement(e,i);return i.addClass("ck-oe-oembed",r),i.setCustomProperty("OembedEntity",!0,r),(0,t.toWidget)(r,i,{label:Drupal.t("OpenEuropa Oembed widget")})}}),Object.keys(this.viewAttrs).forEach((t=>{const i={model:{key:t,name:"oembedEntityInline"},view:{name:"a",key:this.viewAttrs[t]}};e.for("dataDowncast").attributeToAttribute(i),e.for("upcast").attributeToAttribute(i)}))}_generateViewInlineElement(e,t,i=!1){const r=t.createContainerElement("a",{href:e.getAttribute("oembedEntitiesResourceUrl")},{priority:5}),n=t.createText(e.getAttribute("oembedEntitiesResourceLabel"));return t.insert(t.createPositionAt(r,0),n),i?r:t.createContainerElement("span",{},[r])}_generateViewBlockElement(e,t,i=!1){const r=this._generateViewInlineElement(e,t,i);return t.createContainerElement("p",{},[r])}}var s=i("ckeditor5/src/ui.js");function d(e,t,i,r){const n=r.dialogClass?r.dialogClass.split(" "):[];n.push("ui-dialog--narrow"),r.dialogClass=n.join(" "),r.autoResize=window.matchMedia("(min-width: 600px)").matches,r.width="auto";Drupal.ajax({dialog:r,dialogType:"modal",selector:".ckeditor5-dialog-loading-link",url:e,progress:{type:"fullscreen"},submit:{editor_object:t}}).execute(),Drupal.ckeditor5.saveCallback=i}class a extends e.Plugin{static get pluginName(){return"OembedEntitiesUI"}init(){const e=this.editor,t=e.commands.get("oembedEntities"),i=e.config.get("oembedEntities"),{dialogSettings:r={},currentRoute:n,currentRouteParameters:o}=i;i&&Object.keys(i.buttons).forEach((a=>{const l=Drupal.url("oe-oembed-embed/dialog/"+i.format+"/"+a);e.ui.componentFactory.add(a,(c=>{const u=i.buttons[a],m=new s.ButtonView(c);let b=null;if(u.icon.endsWith("svg")){let e=new XMLHttpRequest;e.open("GET",u.icon,!1),e.send(null),b=e.response}return m.set({label:u.label,icon:b??'<?xml version="1.0" standalone="no"?><!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.0//EN"\n  "http://www.w3.org/TR/2001/REC-SVG-20010904/DTD/svg10.dtd"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" id="body_1" viewBox="0 0 64 64"><g transform="matrix(1 0 0 1 0 0)"><path d="M22.5-.5h8c4.03 2.053 6.03 5.387 6 10 10.687-1.132 16.187 3.534 16.5 14-2 2.667-4 2.667-6 0-.233-2.28-1.066-4.28-2.5-6-4.48-1.638-9.147-2.472-14-2.5-1.333-2-1.333-4 0-6-2.928-4.71-5.595-4.543-8 .5 2 5.334.333 7-5 5-2.415-6.477-.749-11.477 5-15z" fill-opacity="0.8235294"/><path d="M-.5 44.5v-4c2.302-1.322 4.635-1.322 7 0 4.914-2.687 4.748-5.353-.5-8-2.163 1.268-4.33 1.268-6.5 0v-15c2.538-4.203 6.205-6.87 11-8a7.93 7.93 0 0 1 3.5 1c.493 1.634.66 3.3.5 5-6.964.305-9.964 3.972-9 11 8.214.399 11.714 4.732 10.5 13-2.389 6.277-6.889 8.443-13.5 6.5l-3-1.5z" fill-opacity="0.8235294"/><path d="M63.5 33.5v6c-1.632 4.615-4.965 6.949-10 7 1.148 7.59-1.518 13.257-8 17h-7c-.973-1.568-1.14-3.235-.5-5a38.053 38.053 0 0 1 6.5-2.5 9.468 9.468 0 0 0 1.5-2.5l1-12c1.865-2.173 4.031-2.507 6.5-1 4-2.667 4-5.333 0-8-4.657 1.974-6.657.641-6-4 7.116-3.786 12.45-2.12 16 5z" fill-opacity="0.8039216"/><path d="M34.5 63.5h-4a55.135 55.135 0 0 1-1.5-9c-2.796-2.483-4.963-1.817-6.5 2 1.322 2.365 1.322 4.698 0 7h-15c-3.667-1.667-6.333-4.333-8-8v-7c1.637-.718 3.303-.885 5-.5.855 7.217 4.688 10.05 11.5 8.5 1.427-8.3 6.26-11.466 14.5-9.5 6.304 4.206 7.637 9.706 4 16.5z" fill-opacity="0.81960785"/></g></svg>',tooltip:!0}),m.bind("isOn","isEnabled").to(t,"value","isEnabled"),this.listenTo(m,"execute",(()=>d(l,{current_route:n,current_route_parameters:o},(({attributes:t})=>{t["data-button-id"]=a,e.execute("oembedEntities",t)}),r))),m}))}))}}class l extends e.Plugin{static get requires(){return[t.WidgetToolbarRepository]}static get pluginName(){return"OembedToolbar"}init(){const t=this.editor,i=t.config.get("oembedEntities"),{dialogSettings:r={},defaultButtons:n,currentRoute:o,currentRouteParameters:a}=i,l=this.editor.plugins.get("OembedEntitiesEditing");t.ui.componentFactory.add("oembedEntityEdit",(c=>{const u=new s.ButtonView(c);return u.set({label:t.t("Edit"),icon:e.icons.pencil,tooltip:!0}),this.listenTo(u,"execute",(()=>{const e=t.model.document.selection.getSelectedElement(),s=this._getButtonId(e,n),c=Drupal.url("oe-oembed-embed/dialog/"+i.format+"/"+s);let u={current_route:o,current_route_parameters:a};for(const[t,i]of Object.entries(l.viewAttrs))e.hasAttribute(t)&&(u[i]=e.getAttribute(t));d(c,u,(({attributes:e})=>{e["data-button-id"]=s,t.execute("oembedEntities",e)}),r)})),u}))}afterInit(){const{editor:e}=this;e.plugins.get(t.WidgetToolbarRepository).register("oembed",{ariaLabel:Drupal.t("OpenEuropa Oembed toolbar"),items:["oembedEntityEdit"],getRelatedElement:e=>{const i=e.getSelectedElement();return i&&(0,t.isWidget)(i)&&i.getCustomProperty("OembedEntity")?i:null}})}_getButtonId(e,t){if(e.hasAttribute("oembedEntitiesButtonId"))return e.getAttribute("oembedEntitiesButtonId");const i=e.getAttribute("oembedEntitiesOembed");return t[Object.keys(t).find((e=>i.includes(`/${e}/`)))]}}class c extends e.Plugin{static get requires(){return[o,a,l]}static get pluginName(){return"OembedEntities"}}const u={OembedEntities:c}})(),r=r.default})()));