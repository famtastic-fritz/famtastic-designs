import { AbsoluteFill, Easing, Img, interpolate, staticFile, useCurrentFrame } from "remotion";

const source = staticFile("02-photoshop-finish.jpg");

export const RattlerAmbient = () => {
  const frame = useCurrentFrame();
  const ease = Easing.bezier(0.22, 0.9, 0.2, 1);
  const zoom = interpolate(frame, [0, 119], [1, 1.095], {
    easing: ease,
    extrapolateRight: "clamp",
  });
  const driftX = interpolate(frame, [0, 119], [-0.8, 1.1], { easing: ease });
  const driftY = interpolate(frame, [0, 119], [0.6, -1.6], { easing: ease });
  const flareX = interpolate(frame, [0, 119], [-20, 115], { easing: ease });
  const flareOpacity = interpolate(frame, [0, 18, 95, 119], [0, 0.24, 0.14, 0], {
    extrapolateRight: "clamp",
  });
  const patternOpacity = interpolate(frame, [0, 30, 100, 119], [0, 0.11, 0.07, 0], {
    extrapolateRight: "clamp",
  });

  return (
    <AbsoluteFill style={{ backgroundColor: "#080b0b", overflow: "hidden" }}>
      <Img
        src={source}
        style={{
          width: "100%",
          height: "100%",
          objectFit: "cover",
          scale: zoom,
          translate: `${driftX}% ${driftY}%`,
          transformOrigin: "53% 49%",
        }}
      />
      <AbsoluteFill
        style={{
          background:
            "radial-gradient(ellipse at 12% 77%, rgba(255,195,90,0.19), transparent 41%), linear-gradient(92deg, rgba(5,12,11,0.15), transparent 44%, rgba(4,7,7,0.2))",
        }}
      />
      <AbsoluteFill
        style={{
          opacity: patternOpacity,
          backgroundImage:
            "linear-gradient(30deg, rgba(210,170,90,0.18) 12%, transparent 12.5%, transparent 87%, rgba(210,170,90,0.18) 87.5%), linear-gradient(150deg, rgba(210,170,90,0.18) 12%, transparent 12.5%, transparent 87%, rgba(210,170,90,0.18) 87.5%)",
          backgroundSize: "48px 84px",
          backgroundPosition: `${frame * 0.45}px ${frame * -0.2}px`,
          mixBlendMode: "screen",
        }}
      />
      <AbsoluteFill
        style={{
          opacity: flareOpacity,
          transform: `translateX(${flareX}%) skewX(-18deg)`,
          background:
            "linear-gradient(90deg, transparent 35%, rgba(255,217,140,0.26) 48%, rgba(255,244,211,0.07) 54%, transparent 68%)",
          mixBlendMode: "screen",
        }}
      />
      <AbsoluteFill
        style={{
          background:
            "linear-gradient(180deg, rgba(1,5,5,0.18), transparent 30%, transparent 66%, rgba(2,6,5,0.42))",
        }}
      />
    </AbsoluteFill>
  );
};
