import { THEMES, dimsForAspect } from './themes.mjs';
import { escapeHtml } from './util/sanitize.mjs';

const LOGO_TOP_RIGHT = 'position:absolute; top:48px; right:48px; width:140px; height:auto; z-index:10;';

export function buildSceneHtml(scene, heroImagePath, themeCode, aspect) {
  const theme = THEMES[themeCode] || THEMES.T1;
  const { width, height } = dimsForAspect(aspect);

  const kineticAttr = scene.kinetic ? 'data-kinetic="true"' : '';
  const heroLayer = scene.heroSource === 'theme_solid'
    ? `<div style="position:absolute; inset:0; background:${theme.bg};
          background-image:
            radial-gradient(circle at 20% 20%, rgba(255,255,255,.08), transparent 50%),
            radial-gradient(circle at 80% 80%, ${theme.accent}22, transparent 50%);"></div>`
    : `<img src="file://${heroImagePath}" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;" />`;

  const overlayColor = theme.bg;
  const overlay = `<div style="position:absolute; inset:0; background:linear-gradient(180deg, transparent 0%, ${overlayColor}D9 60%, ${overlayColor} 100%);"></div>`;

  const logo = `<img src="__LOGO_DATA_URI__" style="${LOGO_TOP_RIGHT}" alt="Pinnacle" />`;

  const captionEn = escapeHtml(scene.captionEn);
  const captionEs = escapeHtml(scene.captionEs || '');

  let captionBlock;
  if (scene.layoutType === 'hook') {
    captionBlock = `
      <div style="position:absolute; inset:0; display:flex; flex-direction:column; justify-content:center; align-items:center; padding:96px; text-align:center; z-index:5;">
        <div style="font-family:Montserrat,sans-serif; font-weight:900; font-size:128px; line-height:1.05; color:${theme.text}; text-shadow:0 4px 32px rgba(0,0,0,.6);">${captionEn}</div>
        ${captionEs ? `<div style="font-family:Montserrat,sans-serif; font-weight:500; font-size:56px; margin-top:32px; color:${theme.muted}; opacity:.92;">${captionEs}</div>` : ''}
      </div>`;
  } else if (scene.layoutType === 'cta') {
    captionBlock = `
      <div style="position:absolute; inset:0; display:flex; flex-direction:column; justify-content:center; align-items:center; padding:80px; text-align:center; z-index:5;">
        <div style="font-family:Montserrat,sans-serif; font-weight:900; font-size:96px; line-height:1.1; color:${theme.text};">${captionEn}</div>
        ${captionEs ? `<div style="font-family:Montserrat,sans-serif; font-weight:500; font-size:48px; margin-top:24px; color:${theme.muted};">${captionEs}</div>` : ''}
        <div style="margin-top:64px; font-family:Montserrat,sans-serif; font-weight:700; font-size:56px; color:${theme.accent};">(920) 777-9886</div>
        <div style="margin-top:16px; font-family:Montserrat,sans-serif; font-weight:500; font-size:40px; color:${theme.text}; opacity:.85;">pinnaclegroupwi.com</div>
      </div>`;
  } else {
    captionBlock = `
      <div style="position:absolute; left:0; right:0; bottom:0; padding:80px 64px 96px 64px; background:linear-gradient(180deg, transparent 0%, ${overlayColor}B3 100%); backdrop-filter:blur(6px); z-index:5;">
        <div style="font-family:Montserrat,sans-serif; font-weight:900; font-size:96px; line-height:1.05; color:${theme.accent};">${captionEn}</div>
        ${captionEs ? `<div style="font-family:Montserrat,sans-serif; font-weight:500; font-size:48px; margin-top:20px; color:${theme.muted};">${captionEs}</div>` : ''}
      </div>`;
  }

  return `
<div ${kineticAttr} style="position:relative; width:${width}px; height:${height}px; overflow:hidden; background:${theme.bg};">
  ${heroLayer}
  ${overlay}
  ${captionBlock}
  ${logo}
</div>`;
}
