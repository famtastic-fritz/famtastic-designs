import { useEffect, useRef } from 'react';

/**
 * Canvas neural-network particle field (v2 original — v1 ships no particle
 * hero, so this is the clean implementation the spec calls for): drifting
 * nodes in brand lime, linked by faint edges when within range, gently
 * attracted toward the pointer. Pauses when offscreen, respects
 * prefers-reduced-motion, and scales with devicePixelRatio.
 */
export default function ParticleField({ className = '', density = 9000 }) {
  const canvasRef = useRef(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return undefined;
    const ctx = canvas.getContext('2d');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let width = 0;
    let height = 0;
    let particles = [];
    let rafId = 0;
    let running = true;
    const pointer = { x: null, y: null };

    const LINK_DIST = 130;
    const LIME = '124, 252, 0';

    function resize() {
      const rect = canvas.getBoundingClientRect();
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      width = rect.width;
      height = rect.height;
      canvas.width = Math.max(1, Math.floor(width * dpr));
      canvas.height = Math.max(1, Math.floor(height * dpr));
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

      const target = Math.max(24, Math.min(110, Math.floor((width * height) / density)));
      particles = Array.from({ length: target }, () => ({
        x: Math.random() * width,
        y: Math.random() * height,
        vx: (Math.random() - 0.5) * 0.45,
        vy: (Math.random() - 0.5) * 0.45,
        r: 1 + Math.random() * 1.6,
      }));
    }

    function step() {
      if (!running) return;
      ctx.clearRect(0, 0, width, height);

      // Edges first so nodes paint on top.
      for (let i = 0; i < particles.length; i += 1) {
        const a = particles[i];
        for (let j = i + 1; j < particles.length; j += 1) {
          const b = particles[j];
          const dx = a.x - b.x;
          const dy = a.y - b.y;
          const dist = Math.hypot(dx, dy);
          if (dist < LINK_DIST) {
            const alpha = (1 - dist / LINK_DIST) * 0.28;
            ctx.strokeStyle = `rgba(${LIME}, ${alpha})`;
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(a.x, a.y);
            ctx.lineTo(b.x, b.y);
            ctx.stroke();
          }
        }
      }

      for (const p of particles) {
        // Gentle pointer attraction.
        if (pointer.x !== null) {
          const dx = pointer.x - p.x;
          const dy = pointer.y - p.y;
          const dist = Math.hypot(dx, dy);
          if (dist < 180 && dist > 0.001) {
            p.vx += (dx / dist) * 0.012;
            p.vy += (dy / dist) * 0.012;
          }
        }

        p.x += p.vx;
        p.y += p.vy;

        // Dampen so pointer pull never spirals.
        p.vx *= 0.995;
        p.vy *= 0.995;
        if (Math.abs(p.vx) < 0.08) p.vx += (Math.random() - 0.5) * 0.01;
        if (Math.abs(p.vy) < 0.08) p.vy += (Math.random() - 0.5) * 0.01;

        // Wrap around edges.
        if (p.x < -10) p.x = width + 10;
        if (p.x > width + 10) p.x = -10;
        if (p.y < -10) p.y = height + 10;
        if (p.y > height + 10) p.y = -10;

        ctx.fillStyle = `rgba(${LIME}, 0.75)`;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fill();
      }

      rafId = window.requestAnimationFrame(step);
    }

    function start() {
      if (!running) {
        running = true;
        rafId = window.requestAnimationFrame(step);
      }
    }
    function stop() {
      running = false;
      window.cancelAnimationFrame(rafId);
    }

    function onPointerMove(event) {
      const rect = canvas.getBoundingClientRect();
      pointer.x = event.clientX - rect.left;
      pointer.y = event.clientY - rect.top;
    }
    function onPointerLeave() {
      pointer.x = null;
      pointer.y = null;
    }

    resize();

    if (reduceMotion) {
      // Static single frame for reduced-motion users.
      running = true;
      step();
      stop();
    } else {
      rafId = window.requestAnimationFrame(step);
    }

    const observer = new IntersectionObserver(([entry]) => {
      if (reduceMotion) return;
      if (entry.isIntersecting) start();
      else stop();
    });
    observer.observe(canvas);

    window.addEventListener('resize', resize);
    canvas.addEventListener('pointermove', onPointerMove);
    canvas.addEventListener('pointerleave', onPointerLeave);

    return () => {
      stop();
      observer.disconnect();
      window.removeEventListener('resize', resize);
      canvas.removeEventListener('pointermove', onPointerMove);
      canvas.removeEventListener('pointerleave', onPointerLeave);
    };
  }, [density]);

  return <canvas ref={canvasRef} className={`v1-particle-field ${className}`} aria-hidden="true" />;
}
