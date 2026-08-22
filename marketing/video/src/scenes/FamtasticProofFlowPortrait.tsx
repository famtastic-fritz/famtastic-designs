import React from 'react';
import {
  AbsoluteFill,
  Easing,
  interpolate,
  Sequence,
  spring,
  useCurrentFrame,
  useVideoConfig,
} from 'remotion';

const palette = {
  ink: '#07130e',
  forest: '#123f2b',
  lime: '#b9f35c',
  cream: '#fff1d3',
  copper: '#ef8f3a',
  rose: '#ef3f8f',
  mist: '#dcebd9',
};

const display = 'Arial Black, Arial, sans-serif';
const editorial = 'Georgia, serif';
const evidence = 'SFMono-Regular, Menlo, Monaco, Consolas, monospace';

const clamp = (value: number, input: number[], output: number[]) =>
  interpolate(value, input, output, {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
  });

const Grain: React.FC<{opacity?: number}> = ({opacity = 0.1}) => (
  <AbsoluteFill
    style={{
      opacity,
      pointerEvents: 'none',
      backgroundImage:
        'url("data:image/svg+xml,%3Csvg viewBox=\'0 0 180 180\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cfilter id=\'n\'%3E%3CfeTurbulence type=\'fractalNoise\' baseFrequency=\'.82\' numOctaves=\'3\' stitchTiles=\'stitch\'/%3E%3C/filter%3E%3Crect width=\'100%25\' height=\'100%25\' filter=\'url(%23n)\'/%3E%3C/svg%3E")',
    }}
  />
);

const Lattice: React.FC<{tone?: string}> = ({tone = 'rgba(185,243,92,.16)'}) => (
  <AbsoluteFill
    style={{
      pointerEvents: 'none',
      backgroundImage: `linear-gradient(45deg, transparent 46%, ${tone} 47% 49%, transparent 50%), linear-gradient(-45deg, transparent 46%, rgba(239,143,58,.12) 47% 49%, transparent 50%)`,
      backgroundSize: '72px 72px',
      backgroundPosition: '0 0, 36px 36px',
    }}
  />
);

const CornerMark: React.FC<{label: string; inverse?: boolean}> = ({label, inverse = false}) => (
  <>
    <div
      style={{
        position: 'absolute',
        top: 72,
        left: 68,
        fontFamily: evidence,
        fontSize: 22,
        fontWeight: 800,
        letterSpacing: 4,
        color: inverse ? palette.ink : palette.cream,
      }}
    >
      FAMTASTIC / {label}
    </div>
    <div
      style={{
        position: 'absolute',
        top: 62,
        right: 68,
        width: 54,
        height: 54,
        border: `3px solid ${inverse ? palette.ink : palette.lime}`,
        borderRadius: 99,
        boxShadow: `0 0 24px ${inverse ? 'rgba(7,19,14,.25)' : 'rgba(185,243,92,.55)'}`,
      }}
    />
  </>
);

const FlowStep: React.FC<{
  index: string;
  label: string;
  detail: string;
  accent: string;
  start: number;
}> = ({index, label, detail, accent, start}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const local = frame - start;
  const enter = spring({frame: Math.max(0, local), fps, config: {damping: 16, stiffness: 140}});
  const pulse = clamp(local, [0, 14, 34, 48], [0.75, 1, 1, 0.9]);
  return (
    <div
      style={{
        display: 'flex',
        gap: 28,
        alignItems: 'flex-start',
        opacity: local < 0 ? 0 : enter,
        translate: `${clamp(enter, [0, 1], [120, 0])}px 0`,
      }}
    >
      <div
        style={{
          width: 90,
          height: 90,
          borderRadius: 99,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          background: accent,
          color: palette.ink,
          fontFamily: evidence,
          fontSize: 28,
          fontWeight: 900,
          scale: pulse,
          boxShadow: `0 0 36px ${accent}`,
        }}
      >
        {index}
      </div>
      <div style={{maxWidth: 690}}>
        <div
          style={{
            fontFamily: display,
            fontSize: 62,
            lineHeight: 0.92,
            letterSpacing: -3.5,
            color: palette.cream,
            textTransform: 'uppercase',
          }}
        >
          {label}
        </div>
        <div style={{fontFamily: editorial, color: palette.mist, fontSize: 34, lineHeight: 1.2, marginTop: 10}}>
          {detail}
        </div>
      </div>
    </div>
  );
};

const Intro: React.FC = () => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const enter = spring({frame, fps, config: {damping: 18, stiffness: 95}});
  const questionY = clamp(enter, [0, 1], [110, 0]);
  const signal = clamp(frame, [15, 72, 115], [0, 1, 0]);
  return (
    <AbsoluteFill style={{background: `radial-gradient(circle at 80% 17%, ${palette.forest} 0%, ${palette.ink} 43%, #030705 100%)`, color: palette.cream, overflow: 'hidden'}}>
      <Lattice />
      <Grain />
      <CornerMark label="PROOF FLOW" />
      <div
        style={{
          position: 'absolute',
          right: -115,
          top: 220,
          width: 730,
          height: 730,
          borderRadius: 999,
          border: `4px solid ${palette.lime}`,
          opacity: 0.52,
          scale: clamp(frame, [0, 120], [0.55, 1.14]),
          rotate: `${clamp(frame, [0, 120], [-20, 22])}deg`,
        }}
      />
      <div style={{position: 'absolute', left: 68, right: 68, top: 550, translate: `0 ${questionY}px`, opacity: enter}}>
        <div style={{fontFamily: evidence, fontSize: 24, letterSpacing: 5, fontWeight: 900, color: palette.lime}}>YOUR IDEA DESERVES MORE THAN A TEMPLATE.</div>
        <div style={{fontFamily: display, fontSize: 146, lineHeight: 0.79, letterSpacing: -12, marginTop: 54, textTransform: 'uppercase'}}>
          MAKE IT
          <br />
          <span style={{color: palette.lime}}>FAMTASTIC.</span>
        </div>
        <div style={{fontFamily: editorial, fontSize: 49, fontStyle: 'italic', color: palette.cream, marginTop: 54, maxWidth: 770, lineHeight: 1.1}}>
          A clear path from “I have an idea” to “I can finally see it.”
        </div>
      </div>
      <div style={{position: 'absolute', bottom: 92, left: 68, height: 10, width: 700, background: 'rgba(255,241,211,.18)'}}>
        <div style={{width: `${signal * 100}%`, height: '100%', background: palette.copper, boxShadow: `0 0 28px ${palette.copper}`}} />
      </div>
    </AbsoluteFill>
  );
};

const Process: React.FC = () => {
  const frame = useCurrentFrame();
  const phase = Math.floor(frame / 100);
  return (
    <AbsoluteFill style={{background: palette.ink, overflow: 'hidden'}}>
      <Lattice tone="rgba(185,243,92,.1)" />
      <Grain opacity={0.08} />
      <CornerMark label="HOW IT MOVES" />
      <div style={{position: 'absolute', left: 68, right: 68, top: 266}}>
        <div style={{fontFamily: editorial, color: palette.copper, fontStyle: 'italic', fontSize: 56, marginBottom: 34}}>The simple part is intentional.</div>
        <FlowStep index="01" label="Tell us the idea." detail="Your business, your people, your energy." accent={palette.lime} start={0} />
        <div style={{height: 56, borderLeft: `3px dashed ${phase >= 1 ? palette.copper : 'rgba(255,241,211,.22)'}`, margin: '12px 0 12px 44px'}} />
        <FlowStep index="02" label="See real directions." detail="Compare concepts before you commit to one." accent={palette.copper} start={100} />
        <div style={{height: 56, borderLeft: `3px dashed ${phase >= 2 ? palette.rose : 'rgba(255,241,211,.22)'}`, margin: '12px 0 12px 44px'}} />
        <FlowStep index="03" label="Choose your favorite." detail="Then we refine the one that feels like you." accent={palette.rose} start={200} />
      </div>
      <div style={{position: 'absolute', bottom: 84, left: 68, right: 68, display: 'flex', justifyContent: 'space-between', fontFamily: evidence, fontSize: 22, letterSpacing: 3, color: palette.mist}}>
        <span>IDEA → PROOF → DIRECTION</span>
        <span>{String(Math.min(phase + 1, 3)).padStart(2, '0')} / 03</span>
      </div>
    </AbsoluteFill>
  );
};

const Proof: React.FC = () => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const tilt = clamp(frame, [0, 160], [-8, 6]);
  const cards = [
    {label: 'CLEAR', color: palette.cream, ink: palette.ink, x: 0, y: 0},
    {label: 'BOLD', color: palette.copper, ink: palette.ink, x: 212, y: 150},
    {label: 'OMG', color: palette.lime, ink: palette.ink, x: 58, y: 420},
  ];
  return (
    <AbsoluteFill style={{background: palette.cream, overflow: 'hidden'}}>
      <Lattice tone="rgba(7,19,14,.11)" />
      <Grain opacity={0.07} />
      <CornerMark label="THE MOMENT" inverse />
      <div style={{position: 'absolute', left: 68, right: 68, top: 260, color: palette.ink}}>
        <div style={{fontFamily: display, fontSize: 124, lineHeight: 0.79, letterSpacing: -10, textTransform: 'uppercase'}}>YOU SHOULD</div>
        <div style={{fontFamily: editorial, fontSize: 81, lineHeight: 0.8, fontStyle: 'italic', color: palette.rose, marginTop: 20}}>feel the difference.</div>
      </div>
      <div style={{position: 'absolute', left: 80, top: 860, width: 920, height: 740, rotate: `${tilt}deg`}}>
        {cards.map((card, index) => {
          const reveal = spring({frame: Math.max(0, frame - index * 25), fps, config: {damping: 16, stiffness: 120}});
          return (
            <div
              key={card.label}
              style={{
                position: 'absolute',
                left: card.x,
                top: card.y,
                width: 570,
                height: 270,
                padding: '40px 38px',
                background: card.color,
                boxShadow: `20px 24px 0 ${palette.ink}`,
                border: `4px solid ${palette.ink}`,
                opacity: reveal,
                scale: clamp(reveal, [0, 1], [0.72, 1]),
                translate: `${clamp(reveal, [0, 1], [100, 0])}px 0`,
                rotate: `${index === 0 ? -4 : index === 1 ? 5 : -2}deg`,
              }}
            >
              <div style={{fontFamily: evidence, fontSize: 21, fontWeight: 900, letterSpacing: 4, color: card.ink}}>A DIRECTION, NOT A COPY</div>
              <div style={{fontFamily: display, fontSize: 82, lineHeight: 0.8, letterSpacing: -6, color: card.ink, marginTop: 28}}>{card.label}</div>
            </div>
          );
        })}
      </div>
      <div style={{position: 'absolute', bottom: 90, left: 68, right: 68, fontFamily: editorial, fontSize: 38, lineHeight: 1.12, color: palette.ink}}>Start with clarity. Leave with a direction you can stand behind.</div>
    </AbsoluteFill>
  );
};

const Close: React.FC = () => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const enter = spring({frame, fps, config: {damping: 20, stiffness: 100}});
  return (
    <AbsoluteFill style={{background: `radial-gradient(circle at 50% 10%, #2f6840 0%, ${palette.ink} 45%, #030705 100%)`, color: palette.cream, overflow: 'hidden'}}>
      <Lattice tone="rgba(239,63,143,.17)" />
      <Grain />
      <CornerMark label="READY WHEN YOU ARE" />
      <div style={{position: 'absolute', left: 68, right: 68, top: 520, textAlign: 'center', opacity: enter, scale: clamp(enter, [0, 1], [0.86, 1])}}>
        <div style={{fontFamily: evidence, fontWeight: 900, fontSize: 24, letterSpacing: 5, color: palette.lime}}>A WEBSITE SHOULD FEEL LIKE A BEGINNING.</div>
        <div style={{fontFamily: display, fontSize: 138, letterSpacing: -11, lineHeight: 0.79, marginTop: 48, textTransform: 'uppercase'}}>LET'S BUILD</div>
        <div style={{fontFamily: editorial, fontStyle: 'italic', color: palette.copper, fontSize: 118, letterSpacing: -8, lineHeight: 0.86}}>your next move.</div>
        <div style={{margin: '82px auto 0', width: 585, padding: '25px 32px', background: palette.lime, color: palette.ink, fontFamily: display, fontSize: 38, letterSpacing: 1, boxShadow: `14px 16px 0 ${palette.rose}`}}>FAMTASTICDESIGNS.COM</div>
      </div>
      <div style={{position: 'absolute', bottom: 80, left: 68, right: 68, display: 'flex', justifyContent: 'space-between', fontFamily: evidence, fontSize: 19, letterSpacing: 3, color: palette.mist}}>
        <span>AI SOLUTIONS STUDIO</span>
        <span>MAKE IT FAMTASTIC</span>
      </div>
    </AbsoluteFill>
  );
};

export const FamtasticProofFlowPortrait: React.FC = () => (
  <AbsoluteFill>
    <Sequence from={0} durationInFrames={165}>
      <Intro />
    </Sequence>
    <Sequence from={165} durationInFrames={315}>
      <Process />
    </Sequence>
    <Sequence from={480} durationInFrames={240}>
      <Proof />
    </Sequence>
    <Sequence from={720} durationInFrames={240}>
      <Close />
    </Sequence>
  </AbsoluteFill>
);
