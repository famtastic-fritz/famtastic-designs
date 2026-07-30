import { motion } from 'framer-motion';

/**
 * Shared motion wrappers porting the v1 (@vueuse/motion) entrance language:
 * fade-up with a soft stagger for grouped items, `whileInView` so sections
 * animate in once as they enter the viewport (v-motion visibleOnce equivalent).
 */

export const fadeUp = {
  hidden: { opacity: 0, y: 28 },
  visible: { opacity: 1, y: 0 },
};

/** Single element that fades up when scrolled into view. */
export function FadeUp({ children, delay = 0, className, as = 'div', ...rest }) {
  const Tag = motion[as] ?? motion.div;
  return (
    <Tag
      className={className}
      variants={fadeUp}
      initial="hidden"
      whileInView="visible"
      viewport={{ once: true, margin: '-60px' }}
      transition={{ duration: 0.55, delay, ease: [0.22, 1, 0.36, 1] }}
      {...rest}
    >
      {children}
    </Tag>
  );
}

/** Parent container — orchestrates a stagger across <Item> children. */
export function Stagger({ children, className, stagger = 0.08, delay = 0, ...rest }) {
  return (
    <motion.div
      className={className}
      initial="hidden"
      whileInView="visible"
      viewport={{ once: true, margin: '-60px' }}
      variants={{
        hidden: {},
        visible: { transition: { staggerChildren: stagger, delayChildren: delay } },
      }}
      {...rest}
    >
      {children}
    </motion.div>
  );
}

/** Child of <Stagger> (also usable standalone). */
export function Item({ children, className, ...rest }) {
  return (
    <motion.div
      className={className}
      variants={fadeUp}
      transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
      {...rest}
    >
      {children}
    </motion.div>
  );
}
