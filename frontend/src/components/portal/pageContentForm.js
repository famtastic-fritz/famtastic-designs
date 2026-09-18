// Undefined preserves saved copy; an explicit empty list withdraws supplied copy.
export function pageContentFromForm(formData) {
  if (!formData.has('page_content_present')) return undefined;
  const fields = ['page_name', 'title', 'heading', 'description', 'body'];
  return formData.getAll('page_copy_page_name').map((_, index) => Object.fromEntries(fields.map(field => [field, String(formData.getAll(`page_copy_${field}`)[index] || '')])));
}
