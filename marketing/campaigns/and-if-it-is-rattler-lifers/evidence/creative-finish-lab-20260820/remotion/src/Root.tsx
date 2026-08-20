import { Composition } from "remotion";
import { RattlerAmbient } from "./RattlerAmbient";

export const FinishLabRoot = () => (
  <Composition
    id="RattlerAmbientFinish"
    component={RattlerAmbient}
    durationInFrames={120}
    fps={30}
    width={1920}
    height={1080}
  />
);
