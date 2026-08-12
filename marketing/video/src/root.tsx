import React from 'react';
import {Composition} from 'remotion';
import {FiftyFiveCentsPortrait} from './scenes/FiftyFiveCentsPortrait';

export const FamtasticVideoRoot: React.FC = () => (
  <Composition
    id="Famtastic55CentsPortrait"
    component={FiftyFiveCentsPortrait}
    durationInFrames={450}
    fps={30}
    width={1080}
    height={1920}
  />
);
