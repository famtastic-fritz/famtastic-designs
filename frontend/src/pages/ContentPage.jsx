import { useParams } from 'react-router-dom';
import NodeList from '../components/NodeList.jsx';

/**
 * Generic listing page for any node bundle, e.g. /content/article or
 * /content/page. The :type param is passed straight through to the JSON:API
 * client.
 */
export default function ContentPage() {
  const { type } = useParams();
  const label = type ? type.charAt(0).toUpperCase() + type.slice(1) : 'Content';

  return (
    <section aria-labelledby="content-heading">
      <div className="section-heading">
        <h2 id="content-heading">{label}</h2>
        <span className="hint">JSON:API · node/{type}</span>
      </div>
      <NodeList type={type} />
    </section>
  );
}
