import React from 'react';
import {AbsoluteFill, interpolate, Sequence, spring, useCurrentFrame, useVideoConfig} from 'remotion';
import {loadFont} from '@remotion/google-fonts/Inter';

const {fontFamily} = loadFont();
const green = '#68ff00';

const Scene: React.FC<{eyebrow: string; headline: React.ReactNode; detail: string}> = ({eyebrow, headline, detail}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const enter = spring({frame, fps, config: {damping: 18, stiffness: 110}});
  return (
    <AbsoluteFill style={{padding: '170px 78px 210px', justifyContent: 'center', background: 'radial-gradient(circle at 70% 18%, #204a18 0%, #0b150d 28%, #050705 68%)', color: '#fff', fontFamily}}>
      <div style={{transform: `translateY(${interpolate(enter, [0, 1], [70, 0])}px)`, opacity: enter}}>
        <div style={{fontSize: 30, color: green, letterSpacing: 5, fontWeight: 800, textTransform: 'uppercase', marginBottom: 30}}>FAMtastic Designs · {eyebrow}</div>
        <div style={{fontSize: 108, lineHeight: 0.96, letterSpacing: -7, fontWeight: 900, maxWidth: 920}}>{headline}</div>
        <div style={{width: 180, height: 10, background: green, margin: '54px 0 42px', boxShadow: `0 0 28px ${green}`}} />
        <div style={{fontSize: 46, lineHeight: 1.2, color: '#d8dfd7', maxWidth: 850}}>{detail}</div>
      </div>
      <div style={{position: 'absolute', bottom: 82, left: 78, fontSize: 30, fontWeight: 700}}>famtasticdesigns.com</div>
      <div style={{position: 'absolute', bottom: 70, right: 78, border: `3px solid ${green}`, borderRadius: 999, padding: '16px 28px', fontSize: 28, fontWeight: 900, color: green}}>START YOUR SITE</div>
    </AbsoluteFill>
  );
};

export const FiftyFiveCentsPortrait: React.FC = () => (
  <AbsoluteFill style={{backgroundColor: '#050705'}}>
    <Sequence from={0} durationInFrames={120}><Scene eyebrow="The excuse ends here" headline={<>Your business needs a <span style={{color: green}}>real home</span> online.</>} detail="Not another profile you rent. A professional website your customers can trust." /></Sequence>
    <Sequence from={120} durationInFrames={105}><Scene eyebrow="Web Basics" headline={<>Just <span style={{color: green}}>55¢</span> a day.</>} detail="$199 flat rate includes a one-page site, first-year basic hosting, and a new domain when needed." /></Sequence>
    <Sequence from={225} durationInFrames={105}><Scene eyebrow="Built for action" headline={<>Clear offer. Clear path. <span style={{color: green}}>More trust.</span></>} detail="Your site gives customers one place to understand you, believe you, and contact you." /></Sequence>
    <Sequence from={330} durationInFrames={120}><Scene eyebrow="FAMtastic Designs" headline={<>Cost is not one of them. <span style={{color: green}}>Period.</span></>} detail="Tell us what your business needs. We will guide the right next step—without forcing every project into the same package." /></Sequence>
  </AbsoluteFill>
);
