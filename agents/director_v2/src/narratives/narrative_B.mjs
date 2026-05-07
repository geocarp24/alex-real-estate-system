// IMPORTANT: All keys are "empty" phrases — NO specific time commitments.
// Rule (Jorge 2026-04-25): PROHIBIDO usar promesas con plazos numéricos
// (ej. "Close in 7 Days") — riesgo legal/compliance si no se cumple.
// USAR comparativos vagos: "Faster Than Banks", "Weeks Not Months", etc.
const HERO_QUERY_TABLE = {
  'faster than banks':   'clock time money',
  'weeks not months':    'calendar keys house',
  'no commissions':      'real estate contract',
  'no showings':         'house closed sign',
  'no repairs':          'home renovation',
  'cash offer':          'cash money deal',
  'any condition':       'vintage house exterior',
  'sell as-is':          'house vintage interior',
};
const FALLBACK_QUERY = 'real estate wisconsin';

export function deriveHeroQuery(heading) {
  const key = String(heading || '').trim().toLowerCase();
  return HERO_QUERY_TABLE[key] || FALLBACK_QUERY;
}

export function expand(spec) {
  const mood = spec.mood || 'upbeat';
  const tier = spec.image_quality === 'premium' ? 'nano_banana' : 'flux_schnell';
  // Allow per-record prompt overrides via spec.prompts.{hook,cta} (premium custom prompts).
  const hookPrompt = spec.prompts?.hook
    || `Modern real estate scene matching: "${spec.hook.en}", Pinnacle Holdings brand, cinematic, golden hour, 9:16 vertical`;
  const ctaPrompt  = spec.prompts?.cta
    || 'Pinnacle Holdings Group branded CTA scene, modern craftsman home exterior at twilight, cinematic, 9:16 vertical';

  // Scale scene durations proportionally so total matches spec.duration.
  // BASE = [3,3,3,3,3] → equal per-slide budget for "3s per slide" rule
  // (Jorge 2026-05-07: 5 slides x 3s = 15s output with xfade overlap accounted).
  // Default duration 17 gives 14.6s output after 4 xfade x 0.6s overlap.
  const BASE = [3.0, 3.0, 3.0, 3.0, 3.0];
  const target = Number(spec.duration) || 17;
  const factor = target / BASE.reduce((a, b) => a + b, 0);
  const D = BASE.map(d => +(d * factor).toFixed(2));

  return [
    {
      index: 1, duration: D[0], layoutType: 'hook',
      captionEn: spec.hook.en, captionEs: spec.hook.es,
      heroSource: tier, heroPrompt: hookPrompt, heroQuery: null,
      kinetic: true, zoompan: { from: 1.0, to: 1.05 },
      transitionOut: 'crossfade', mood,
    },
    {
      index: 2, duration: D[1], layoutType: 'point',
      captionEn: spec.points[0].headingEn, captionEs: spec.points[0].headingEs,
      heroSource: 'pexels', heroPrompt: spec.points[0].heroPrompt || null, heroQuery: deriveHeroQuery(spec.points[0].headingEn),
      kinetic: false, zoompan: { from: 1.0, to: 1.03 },
      transitionOut: 'crossfade', mood,
    },
    {
      index: 3, duration: D[2], layoutType: 'point',
      captionEn: spec.points[1].headingEn, captionEs: spec.points[1].headingEs,
      heroSource: 'pexels', heroPrompt: spec.points[1].heroPrompt || null, heroQuery: deriveHeroQuery(spec.points[1].headingEn),
      kinetic: false, zoompan: { from: 1.0, to: 1.03 },
      transitionOut: 'crossfade', mood,
    },
    {
      index: 4, duration: D[3], layoutType: 'point',
      captionEn: spec.points[2].headingEn, captionEs: spec.points[2].headingEs,
      heroSource: 'pexels', heroPrompt: spec.points[2].heroPrompt || null, heroQuery: deriveHeroQuery(spec.points[2].headingEn),
      kinetic: false, zoompan: { from: 1.0, to: 1.03 },
      transitionOut: 'crossfade', mood,
    },
    {
      index: 5, duration: D[4], layoutType: 'cta',
      captionEn: spec.cta.en, captionEs: spec.cta.es,
      heroSource: tier, heroPrompt: ctaPrompt, heroQuery: null,
      kinetic: true, zoompan: { from: 1.0, to: 1.05 },
      transitionOut: 'none', mood,
    },
  ];
}
