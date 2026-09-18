import { useId, useState } from 'react';

export default function PortalPageContentFields({ request }) {
  const labelId = useId();
  const [pages, setPages] = useState(() => (request.intake?.authored_content?.pages || []).map(record => record.text));
  const [changed, setChanged] = useState(false);
  const update = (index, field, value) => { setChanged(true); setPages(current => current.map((page, i) => i === index ? { ...page, [field]: value } : page)); };
  return <details className="portal-form-group">
    <summary>Page copy</summary>
    <p>Write or paste the words you want on each page. You can save unfinished copy and come back to it.</p>
    {(changed || pages.length > 0) && <input type="hidden" name="page_content_present" value="1" />}
    {pages.map((page, index) => <fieldset key={index}>
      <legend>{page.page_name || `Page ${index + 1}`}</legend>
      {[['page_name', 'Page name'], ['title', 'Browser title'], ['heading', 'Page heading'], ['description', 'Short page description'], ['body', 'Page text']].map(([field, label]) => <label key={field}>
        <span id={`${labelId}-${index}-${field}`}>{label}</span>
        {field === 'body' ? <textarea aria-labelledby={`${labelId}-${index}-${field}`} name={`page_copy_${field}`} value={page[field] || ''} onChange={event => update(index, field, event.target.value)} />
          : <input aria-labelledby={`${labelId}-${index}-${field}`} name={`page_copy_${field}`} value={page[field] || ''} required={field === 'page_name'} onChange={event => update(index, field, event.target.value)} />}
      </label>)}
      <button type="button" onClick={() => { setChanged(true); setPages(current => current.filter((_, i) => i !== index)); }}>Remove this page copy</button>
    </fieldset>)}
    <button type="button" onClick={() => { setChanged(true); setPages(current => [...current, {}]); }}>Add page copy</button>
  </details>;
}
