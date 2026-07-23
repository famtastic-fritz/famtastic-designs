import { Stagger, Item } from './motion.jsx';
import { Strip } from './Section.jsx';

/**
 * v1 stats bar — tinted border band of big lime values + muted labels,
 * driven by the homepage field_stats_items metric paragraphs.
 */
export default function StatsBar({ items = [] }) {
  if (!items.length) return null;
  return (
    <Strip label="Key numbers">
      <Stagger className="v1-stats" stagger={0.1}>
        {items.map((item, i) => (
          <Item key={item.id ?? `${item.value}-${i}`} className="v1-stats__item">
            <span className="v1-stats__value">{item.value}</span>
            <span className="v1-stats__label">{item.label}</span>
          </Item>
        ))}
      </Stagger>
    </Strip>
  );
}
