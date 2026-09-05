import React from 'react';
import {Composition} from 'remotion';
import {FiftyFiveCentsPortrait} from './scenes/FiftyFiveCentsPortrait';
import {Drop06} from './drop06/Drop06';
import {FPS, TOTAL_FRAMES} from './drop06/script';

export const FamtasticVideoRoot: React.FC = () => (
  <>
    <Composition
      id="Famtastic55CentsPortrait"
      component={FiftyFiveCentsPortrait}
      durationInFrames={450}
      fps={30}
      width={1080}
      height={1920}
    />
    <Composition
      id="Drop06GmailLinktree"
      component={Drop06}
      durationInFrames={TOTAL_FRAMES}
      fps={FPS}
      width={1080}
      height={1920}
    />
  </>
);
