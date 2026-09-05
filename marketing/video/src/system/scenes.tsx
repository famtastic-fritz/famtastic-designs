/**
 * THE ARCHETYPES.
 *
 * One component per layout in CAMPAIGN_ART_DIRECTION_V1's table, each a motion
 * version of the still archetype of the same name in famtastic-social-frame.jsx.
 *
 * Every one of them resolves its geometry from `useKit().f` — the format the
 * renderer actually handed us — so the SAME scene object produces a re-flowed
 * 9:16, 1:1 and 16:9 composition rather than three crops of one master. The
 * headline re-breaks, the columns collapse, the type re-fits, and a plate can
 * swap to art shot in the target aspect. A crop cannot do any of that.
 */
import React from 'react';
import { AbsoluteFill, OffthreadVideo, staticFile, useCurrentFrame } from 'remotion';
import { fitLines, fitSize, safeBox } from './formats';
import {
  BandCut,
  Body,
  Chip,
  Eyebrow,
  FONTS,
  GlowScope,
  Headline,
  Marker,
  Mark,
  Panel,
  Pill,
  PlateArt,
  Signature,
  Text3D,
  useKit,
} from './kit';
import { fadeIn, rise, stagger } from './motion';
import type { Scene } from './types';

type SceneProps<K extends Scene['kind']> = {
  scene: Extract<Scene, { kind: K }>;
  length: number;
  ctaUrl: string;
};

/** Column the type gets. Landscape gives art the right side; portrait does not. */
const typeColumn = (boxW: number, columns: 1 | 2, share = 0.62) =>
  columns === 2 ? Math.round(boxW * share) : boxW;

/* ------------------------------------------------------------------- plate */

export const PlateScene: React.FC<SceneProps<'plate'>> = ({ scene, length }) => {
  const { f } = useKit();
  const box = safeBox(f);
  const col = typeColumn(box.w, f.columns, 0.66);
  const size = fitLines(scene.head, col, f.type.head);
  return (
    <AbsoluteFill>
      <PlateArt plate={scene.plate} length={length} />
      {scene.eyebrow ? (
        <div style={{ position: 'absolute', left: box.x, top: box.y + Math.round(f.type.eyebrow * 2.6) }}>
          <Eyebrow text={scene.eyebrow} />
        </div>
      ) : null}
      <div style={{ position: 'absolute', left: box.x, bottom: f.safeBottom, width: col }}>
        <Headline
          lines={scene.head}
          size={size}
          lead={size * 1.02}
          accentFrom={scene.accentFrom}
          at={8}
          step={9}
          shadow
        />
      </div>
    </AbsoluteFill>
  );
};

/* ------------------------------------------------------------------- split */

/**
 * SPLIT — a full-bleed band of held colour against the ground. The band carries
 * a single statement; the ground carries the explanation. Cox uses this
 * constantly and it is the fastest way out of "text on a field".
 */
export const SplitScene: React.FC<SceneProps<'split'>> = ({ scene, ctaUrl }) => {
  const { t, f } = useKit();
  const frame = useCurrentFrame();
  const box = safeBox(f);
  const bandH = Math.round(f.height * (f.columns === 2 ? 0.5 : 0.42));
  const bandY = box.y;
  const size = fitLines(scene.head, box.w, f.type.head);
  const bodySize = f.type.body;
  return (
    <AbsoluteFill>
      <div
        style={{
          position: 'absolute',
          left: 0,
          right: 0,
          top: bandY,
          height: bandH,
          background: t.panel(0.16),
          opacity: fadeIn(frame, 0, 10),
          overflow: 'hidden',
        }}
      >
        <BandCut height={Math.round(bandH * 0.18)} />
      </div>
      <div
        style={{
          position: 'absolute',
          left: box.x,
          top: bandY + Math.round(bandH * 0.16),
          width: box.w,
        }}
      >
        {scene.eyebrow ? <Eyebrow text={scene.eyebrow} /> : null}
        <div style={{ marginTop: Math.round(f.type.eyebrow * 1.4) }}>
          <Headline lines={scene.head} size={size} lead={size * 1.06} accentFrom={1} at={8} step={9} />
        </div>
      </div>
      <div
        style={{
          position: 'absolute',
          left: box.x,
          top: bandY + bandH + Math.round(f.height * 0.055),
          width: f.columns === 2 ? Math.round(box.w * 0.7) : box.w,
        }}
      >
        {scene.body ? <Body lines={scene.body} size={bodySize} at={22} /> : null}
        {scene.cta ? (
          <div style={{ marginTop: Math.round(bodySize * 1.1) }}>
            <GlowScope on="pill">
              <Pill text={scene.cta} size={Math.round(bodySize * 0.86)} at={34} />
            </GlowScope>
          </div>
        ) : null}
      </div>
      <div style={{ position: 'absolute', left: box.x, right: box.x, bottom: f.safeBottom }}>
        <Signature url={ctaUrl} at={40} />
      </div>
    </AbsoluteFill>
  );
};

/* -------------------------------------------------------------------- stat */

/**
 * STAT — one number very large, plus the sentence that gives it meaning.
 * Use ONLY with a figure verifiable from the repo. No invented statistics.
 */
export const StatScene: React.FC<SceneProps<'stat'>> = ({ scene, ctaUrl }) => {
  const { f } = useKit();
  const box = safeBox(f);
  const two = f.columns === 2;
  const statCol = two ? Math.round(box.w * 0.46) : box.w;
  const statSize = fitSize(scene.stat, statCol, Math.round(f.height * 0.26), 'display');
  const bodySize = Math.round(f.type.body * 1.02);
  const bodyCol = two ? Math.round(box.w * 0.46) : box.w;
  return (
    <AbsoluteFill>
      <div style={{ position: 'absolute', left: box.x, top: box.y + Math.round(f.type.eyebrow * 1.2) }}>
        {scene.eyebrow ? <Eyebrow text={scene.eyebrow} /> : null}
      </div>
      <div
        style={{
          position: 'absolute',
          left: box.x,
          right: box.x,
          top: box.y + Math.round(box.h * (two ? 0.26 : 0.18)),
          display: 'flex',
          flexDirection: two ? 'row' : 'column',
          alignItems: two ? 'center' : 'flex-start',
          gap: two ? Math.round(box.w * 0.06) : Math.round(statSize * 0.34),
        }}
      >
        <div style={{ width: two ? statCol : undefined }}>
          <Text3D text={scene.stat} size={statSize} at={4} />
        </div>
        <div style={{ width: bodyCol }}>
          <Body lines={scene.body} size={bodySize} at={16} />
          {scene.cta ? (
            <div style={{ marginTop: Math.round(bodySize * 1.2) }}>
              <GlowScope on="pill">
                <Pill text={scene.cta} size={Math.round(bodySize * 0.86)} at={30} />
              </GlowScope>
            </div>
          ) : null}
        </div>
      </div>
      <div style={{ position: 'absolute', left: box.x, right: box.x, bottom: f.safeBottom }}>
        <Signature url={ctaUrl} at={38} />
      </div>
    </AbsoluteFill>
  );
};

/* -------------------------------------------------------------- offer-card */

/**
 * OFFER CARD — price is the hero. Badge chip, headline, an enormous number, one
 * line of terms, a CTA pill, on an elevated panel with an angled corner.
 * For the moment a campaign is actually asking for the sale.
 */
export const OfferCardScene: React.FC<SceneProps<'offer-card'>> = ({ scene }) => {
  const { t, f } = useKit();
  const frame = useCurrentFrame();
  const box = safeBox(f);
  const two = f.columns === 2;
  const pad = Math.round(f.width * (two ? 0.038 : 0.055));
  const inner = box.w - pad * 2;
  const priceCol = two ? Math.round(inner * 0.44) : inner;
  const priceSize = fitSize(scene.price, priceCol, Math.round(f.height * (two ? 0.26 : 0.2)), 'display');
  const headSize = fitSize(scene.head, two ? Math.round(inner * 0.5) : inner, Math.round(f.type.head * 0.56), 'display');
  const bodySize = Math.round(f.type.body * 0.92);
  return (
    <AbsoluteFill>
      <div style={{ position: 'absolute', left: box.x, top: box.y + Math.round(f.type.eyebrow * 0.8) }}>
        {scene.eyebrow ? <Eyebrow text={scene.eyebrow} /> : null}
      </div>
      <GlowScope on="panel">
        <div
          style={{
            position: 'absolute',
            left: box.x,
            top: box.y + Math.round(box.h * (two ? 0.16 : 0.13)),
            width: box.w,
          }}
        >
          <Panel mixT={0.12} cutCorner style={{ padding: pad }}>
            <div style={{ display: 'flex', flexDirection: two ? 'row' : 'column', gap: two ? pad : 0 }}>
              <div style={{ width: two ? priceCol : undefined }}>
                <Chip text={scene.chip ?? 'What it costs'} size={Math.round(bodySize * 0.62)} at={6} />
                <div style={{ marginTop: Math.round(bodySize * 0.9) }}>
                  <Text3D text={scene.price} size={priceSize} at={12} />
                </div>
                <div
                  style={{
                    fontFamily: FONTS.body,
                    fontWeight: 600,
                    fontSize: bodySize,
                    color: t.body,
                    marginTop: Math.round(bodySize * 0.7),
                    opacity: fadeIn(frame, 20, 12),
                  }}
                >
                  {scene.terms}
                </div>
              </div>
              <div
                style={{
                  flex: 1,
                  marginTop: two ? 0 : Math.round(bodySize * 1.3),
                  paddingTop: two ? Math.round(bodySize * 0.3) : Math.round(bodySize * 1.3),
                  borderTop: two ? 'none' : `1px solid ${t.hair}`,
                  borderLeft: two ? `1px solid ${t.hair}` : 'none',
                  paddingLeft: two ? pad : 0,
                }}
              >
                <div style={{ marginBottom: Math.round(bodySize * 0.9) }}>
                  <Headline lines={[scene.head]} size={headSize} lead={headSize * 1.04} at={16} />
                </div>
                {scene.cta ? <Pill text={scene.cta} size={Math.round(bodySize * 0.86)} at={26} /> : null}
                {scene.disclosure ? (
                  <div
                    style={{
                      fontFamily: FONTS.body,
                      fontWeight: 500,
                      fontSize: Math.round(bodySize * 0.66),
                      lineHeight: 1.45,
                      color: t.body,
                      marginTop: Math.round(bodySize * 1.1),
                      opacity: fadeIn(frame, 40, 14),
                    }}
                  >
                    {scene.disclosure}
                  </div>
                ) : null}
              </div>
            </div>
          </Panel>
        </div>
      </GlowScope>
    </AbsoluteFill>
  );
};

/* --------------------------------------------------------------- checklist */

/**
 * CHECKLIST — what is actually included. Answers the real objection instead of
 * asserting value. Each row is a marker plus a fact, separated by a hairline.
 */
export const ChecklistScene: React.FC<SceneProps<'checklist'>> = ({ scene, ctaUrl }) => {
  const { t, f } = useKit();
  const frame = useCurrentFrame();
  const box = safeBox(f);
  const two = f.columns === 2;
  const headCol = two ? Math.round(box.w * 0.40) : box.w;
  const rowsCol = two ? Math.round(box.w * 0.52) : box.w;
  const headSize = fitLines(scene.head, headCol, Math.round(f.type.head * 0.82));
  const itemBase = Math.round(f.type.body * 0.98);
  const itemSize = scene.items.reduce(
    (acc, i) => Math.min(acc, fitSize(i, rowsCol - Math.round(itemBase * 2.1), itemBase, 'body')),
    itemBase,
  );
  const rowGap = Math.round(itemSize * 1.5);
  return (
    <AbsoluteFill>
      <div
        style={{
          position: 'absolute',
          left: box.x,
          right: box.x,
          top: box.y + Math.round(box.h * (two ? 0.1 : 0.05)),
          display: 'flex',
          flexDirection: two ? 'row' : 'column',
          gap: two ? Math.round(box.w * 0.08) : Math.round(headSize * 0.6),
        }}
      >
        <div style={{ width: two ? headCol : undefined }}>
          {scene.eyebrow ? <Eyebrow text={scene.eyebrow} /> : null}
          <div style={{ marginTop: Math.round(f.type.eyebrow * 1.3) }}>
            <Headline lines={scene.head} size={headSize} lead={headSize * 1.04} accentFrom={1} at={6} step={8} />
          </div>
        </div>
        <div style={{ width: rowsCol }}>
          {scene.items.map((item, i) => {
            const at = stagger(i, 14, 8);
            return (
              <div
                key={i}
                style={{
                  display: 'flex',
                  alignItems: 'flex-start',
                  gap: Math.round(itemSize * 0.7),
                  paddingTop: rowGap * 0.42,
                  paddingBottom: rowGap * 0.42,
                  borderTop: i === 0 ? 'none' : `1px solid ${t.hair}`,
                  opacity: fadeIn(frame, at, 12),
                  transform: rise(frame, at, 16, 12),
                }}
              >
                <div style={{ paddingTop: Math.round(itemSize * 0.14) }}>
                  <Marker size={Math.round(itemSize * 0.92)} />
                </div>
                <div
                  style={{
                    fontFamily: FONTS.body,
                    fontWeight: 500,
                    fontSize: itemSize,
                    lineHeight: 1.32,
                    color: t.head,
                  }}
                >
                  {item}
                </div>
              </div>
            );
          })}
          {scene.note ? (
            <div
              style={{
                fontFamily: FONTS.body,
                fontWeight: 500,
                fontSize: Math.round(itemSize * 0.66),
                lineHeight: 1.45,
                color: t.body,
                marginTop: Math.round(itemSize * 0.9),
                opacity: fadeIn(frame, stagger(scene.items.length, 14, 8), 14),
              }}
            >
              {scene.note}
            </div>
          ) : null}
        </div>
      </div>
      <div style={{ position: 'absolute', left: box.x, right: box.x, bottom: f.safeBottom }}>
        <Signature url={ctaUrl} at={44} />
      </div>
    </AbsoluteFill>
  );
};

/* --------------------------------------------------------------- statement */

/** The turn of the argument. Type alone, or over a plate. Nothing else. */
export const StatementScene: React.FC<SceneProps<'statement'>> = ({ scene, length }) => {
  const { f } = useKit();
  const box = safeBox(f);
  const col = f.columns === 2 ? Math.round(box.w * 0.78) : box.w;
  const size = fitLines(scene.head, col, Math.round(f.type.head * 1.02));
  return (
    <AbsoluteFill>
      {scene.plate ? <PlateArt plate={scene.plate} length={length} weight={1.25} /> : null}
      <div
        style={{
          position: 'absolute',
          left: box.x,
          top: box.y,
          width: col,
          height: box.h,
          display: 'flex',
          flexDirection: 'column',
          justifyContent: 'center',
        }}
      >
        {scene.eyebrow ? (
          <div style={{ marginBottom: Math.round(f.type.eyebrow * 1.6) }}>
            <Eyebrow text={scene.eyebrow} />
          </div>
        ) : null}
        <Headline
          lines={scene.head}
          size={size}
          lead={size * 1.0}
          accentFrom={scene.head.length - 1}
          at={6}
          step={11}
          shadow={Boolean(scene.plate)}
        />
      </div>
    </AbsoluteFill>
  );
};

/* --------------------------------------------------------------- presenter */

/**
 * PRESENTER — the bought anchor, played inside the kit.
 *
 * Takes render 1920x1080. Full bleed in 16:9. In 9:16 and 1:1 the take sits in a
 * bordered panel on the brand ground: a head-and-shoulders composition
 * centre-cropped to portrait throws away the framing the credits paid for, and
 * a panel reads as deliberate where a crop reads as an accident.
 */
export const PresenterScene: React.FC<SceneProps<'presenter'>> = ({ scene, length }) => {
  const { t, f } = useKit();
  const box = safeBox(f);
  const full = scene.full || f.columns === 2;
  const g = t.p.ground;
  const ground = `rgb(${g[0]},${g[1]},${g[2]})`;

  const video = (
    <OffthreadVideo
      src={staticFile(scene.src)}
      muted={scene.muted ?? false}
      style={{ width: '100%', height: '100%', objectFit: 'cover', objectPosition: '50% 35%' }}
    />
  );

  if (full) {
    return (
      <AbsoluteFill style={{ backgroundColor: ground }}>
        <AbsoluteFill>{video}</AbsoluteFill>
        {scene.eyebrow ? (
          <div style={{ position: 'absolute', left: box.x, top: box.y }}>
            <Eyebrow text={scene.eyebrow} />
          </div>
        ) : null}
      </AbsoluteFill>
    );
  }

  // Portrait / square: panel the take, keep its native 16:9 ratio.
  const panelW = box.w;
  const panelH = Math.round(panelW * (9 / 16));
  const panelY = Math.round(box.y + (box.h - panelH) * 0.42);
  const headSize = scene.head ? fitLines(scene.head, box.w, Math.round(f.type.head * 0.82)) : 0;

  return (
    <AbsoluteFill style={{ backgroundColor: ground }}>
      {scene.eyebrow ? (
        <div style={{ position: 'absolute', left: box.x, top: box.y }}>
          <Eyebrow text={scene.eyebrow} />
        </div>
      ) : null}
      <div
        style={{
          position: 'absolute',
          left: box.x,
          top: panelY,
          width: panelW,
          height: panelH,
          overflow: 'hidden',
          border: `2px solid rgba(${t.p.accent[0]},${t.p.accent[1]},${t.p.accent[2]},0.55)`,
        }}
      >
        {video}
      </div>
      {scene.head ? (
        <div style={{ position: 'absolute', left: box.x, top: panelY + panelH + Math.round(f.type.head * 0.7), width: box.w }}>
          <Headline
            lines={scene.head}
            size={headSize}
            lead={headSize * 1.04}
            accentFrom={scene.head.length - 1}
            at={8}
            step={10}
          />
        </div>
      ) : null}
    </AbsoluteFill>
  );
};

/* ------------------------------------------------------------------- outro */

/** Mark, promise, CTA pill, terms. Always last, always the same shape. */
export const OutroScene: React.FC<SceneProps<'outro'>> = ({ scene, length }) => {
  const { t, f } = useKit();
  const frame = useCurrentFrame();
  const box = safeBox(f);
  const col = Math.round(box.w * (f.columns === 2 ? 0.66 : 0.96));
  const size = fitLines(scene.head, col, Math.round(f.type.head * 0.86));
  const out = fadeIn(frame, 0, 10) * (1 - fadeIn(frame, length - 10, 10));
  return (
    <AbsoluteFill
      style={{ alignItems: 'center', justifyContent: 'center', opacity: out, paddingBottom: f.safeBottom * 0.5 }}
    >
      <div style={{ opacity: fadeIn(frame, 2, 14), transform: rise(frame, 2, 18, 14) }}>
        <Mark size={Math.round(f.type.head * 1.1)} />
      </div>
      <div style={{ marginTop: Math.round(size * 0.5), width: col }}>
        <Headline
          lines={scene.head}
          size={size}
          lead={size * 1.06}
          accentFrom={scene.head.length - 1}
          at={8}
          step={9}
          align="center"
        />
      </div>
      <div style={{ marginTop: Math.round(size * 0.6) }}>
        <GlowScope on="pill">
          <Pill text={scene.cta} size={Math.round(f.type.footer * 0.92)} at={22} variant="outline" />
        </GlowScope>
      </div>
      {scene.terms ? (
        <div
          style={{
            marginTop: Math.round(size * 0.42),
            maxWidth: col,
            textAlign: 'center',
            fontFamily: FONTS.body,
            fontWeight: 500,
            fontSize: Math.round(f.type.footer * 0.72),
            lineHeight: 1.45,
            color: t.body,
            opacity: fadeIn(frame, 30, 14),
          }}
        >
          {scene.terms}
        </div>
      ) : null}
    </AbsoluteFill>
  );
};
