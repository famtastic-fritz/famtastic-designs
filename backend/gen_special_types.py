#!/usr/bin/env python3
"""Generate splash_page + homepage node type config for FAMtastic Designs.

Writes node.type, field.field, core.entity_form_display and
core.entity_view_display (default + teaser) config for the two special
content types into backend/config/site/. Field storages are owned by the
foundation agent and are only referenced here.

Regenerate: python3 gen_special_types.py
Validate:   python3 gen_special_types.py --validate
"""
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "config", "site")
os.makedirs(OUT, exist_ok=True)

FILES = {}


def node_type(t, name, desc):
    FILES[f"node.type.{t}.yml"] = f"""langcode: en
status: true
dependencies: {{  }}
name: '{name}'
type: {t}
description: '{desc}'
help: null
new_revision: true
preview_mode: 1
display_submitted: false
"""


def field(t, fname, label, ftype, module_deps, settings="  {  }", desc="", required=False, extra_settings=None):
    """Generic field.field emitter. extra_settings: raw YAML lines (indented 2) for settings block."""
    deps_cfg = f"    - field.storage.node.{fname}\n    - node.type.{t}"
    deps_mod = "\n".join(f"    - {m}" for m in module_deps)
    if extra_settings is not None:
        settings_block = "settings:\n" + extra_settings
    else:
        settings_block = f"settings: {settings}"
    d = desc.replace("'", "''")
    FILES[f"field.field.node.{t}.{fname}.yml"] = f"""langcode: en
status: true
dependencies:
  config:
{deps_cfg}
  module:
{deps_mod}
id: node.{t}.{fname}
field_name: {fname}
entity_type: node
bundle: {t}
label: '{label}'
description: '{d}'
required: {str(required).lower()}
translatable: true
default_value: {{  }}
default_value_callback: ''
{settings_block}
field_type: {ftype}
"""


def body_field(t):
    FILES[f"field.field.node.{t}.body.yml"] = f"""langcode: en
status: true
dependencies:
  config:
    - field.storage.node.body
    - node.type.{t}
  module:
    - text
id: node.{t}.body
field_name: body
entity_type: node
bundle: {t}
label: Body
description: ''
required: false
translatable: true
default_value: {{  }}
default_value_callback: ''
settings:
  display_summary: true
  required_summary: false
  allowed_formats: {{  }}
field_type: text_with_summary
"""


def er_node_settings(target):
    return f"""  handler: 'default:node'
  handler_settings:
    target_bundles:
      {target}: {target}
    sort:
      field: _none
      direction: ASC
    auto_create: false
    auto_create_bundle: ''"""


def er_media_settings():
    return """  handler: 'default:media'
  handler_settings:
    target_bundles: {  }
    sort:
      field: _none
      direction: ASC
    auto_create: false"""


def err_para_settings(ptype):
    return f"""  handler: 'default:paragraph'
  handler_settings:
    target_bundles:
      {ptype}: {ptype}
    negate: 0
    target_bundles_drag_drop:
      {ptype}:
        weight: -10
        enabled: true"""


def link_settings():
    return """  title: 1
  link_type: 17"""


# ---- widget / formatter snippets ----
def w_string(w):
    return f"""    type: string_textfield
    weight: {w}
    region: content
    settings:
      size: 60
      placeholder: ''
    third_party_settings: {{  }}"""


def w_textarea_string(w):
    return f"""    type: string_textarea
    weight: {w}
    region: content
    settings:
      rows: 5
      placeholder: ''
    third_party_settings: {{  }}"""


def w_textarea_text(w):
    return f"""    type: text_textarea
    weight: {w}
    region: content
    settings:
      rows: 5
      placeholder: ''
    third_party_settings: {{  }}"""


def w_body(w):
    return f"""    type: text_textarea_with_summary
    weight: {w}
    region: content
    settings:
      rows: 9
      summary_rows: 3
      placeholder: ''
      show_summary: false
    third_party_settings: {{  }}"""


def w_options(w):
    return f"""    type: options_select
    weight: {w}
    region: content
    settings: {{  }}
    third_party_settings: {{  }}"""


def w_datetime(w):
    return f"""    type: datetime_datelist
    weight: {w}
    region: content
    settings:
      increment: 15
      date_order: YMD
      time_type: '24'
    third_party_settings: {{  }}"""


def w_link(w):
    return f"""    type: link_default
    weight: {w}
    region: content
    settings:
      placeholder_url: ''
      placeholder_title: ''
    third_party_settings: {{  }}"""


def w_er_autocomplete(w):
    return f"""    type: entity_reference_autocomplete
    weight: {w}
    region: content
    settings:
      match_operator: CONTAINS
      match_limit: 10
      size: 60
      placeholder: ''
    third_party_settings: {{  }}"""


def w_paragraphs(w):
    return f"""    type: paragraphs
    weight: {w}
    region: content
    settings:
      title: Paragraph
      title_plural: Paragraphs
      edit_mode: open
      closed_mode: summary
      autocollapse: none
      closed_mode_threshold: 0
      add_mode: dropdown
      form_display_mode: default
      default_paragraph_type: ''
      features:
        add_above: '0'
        collapse_edit_all: collapse_edit_all
        duplicate: duplicate
    third_party_settings: {{  }}"""


def f_string(w):
    return f"""    type: string
    label: hidden
    settings:
      link_to_entity: false
    third_party_settings: {{  }}
    weight: {w}
    region: content"""


def f_basic_string(w):
    return f"""    type: basic_string
    label: hidden
    settings: {{  }}
    third_party_settings: {{  }}
    weight: {w}
    region: content"""


def f_text(w):
    return f"""    type: text_default
    label: hidden
    settings: {{  }}
    third_party_settings: {{  }}
    weight: {w}
    region: content"""


def f_list(w):
    return f"""    type: list_default
    label: hidden
    settings: {{  }}
    third_party_settings: {{  }}
    weight: {w}
    region: content"""


def f_datetime(w):
    return f"""    type: datetime_default
    label: hidden
    settings:
      timezone_override: ''
      format_type: medium
    third_party_settings: {{  }}
    weight: {w}
    region: content"""


def f_link(w):
    return f"""    type: link
    label: hidden
    settings:
      trim_length: 80
      url_only: false
      url_plain: false
      rel: '0'
      target: '0'
    third_party_settings: {{  }}
    weight: {w}
    region: content"""


def f_er_label(w):
    return f"""    type: entity_reference_label
    label: hidden
    settings:
      link: true
    third_party_settings: {{  }}
    weight: {w}
    region: content"""


def f_er_media_view(w):
    return f"""    type: entity_reference_entity_view
    label: hidden
    settings:
      view_mode: default
      link: false
    third_party_settings: {{  }}
    weight: {w}
    region: content"""


def f_err_view(w):
    return f"""    type: entity_reference_revisions_entity_view
    label: hidden
    settings:
      view_mode: default
      link: ''
    third_party_settings: {{  }}
    weight: {w}
    region: content"""


def form_display(t, field_names, content_map, module_deps):
    cfg_deps = [f"node.type.{t}"] + [f"field.field.node.{t}.{f}" for f in field_names]
    cfg = "\n".join(f"    - {c}" for c in cfg_deps)
    mod = "\n".join(f"    - {m}" for m in module_deps)
    content = "\n".join(f"  {k}:\n{v}" for k, v in content_map)
    FILES[f"core.entity_form_display.node.{t}.default.yml"] = f"""langcode: en
status: true
dependencies:
  config:
{cfg}
  module:
{mod}
id: node.{t}.default
targetEntityType: node
bundle: {t}
mode: default
content:
{content}
hidden: {{  }}
"""


def view_display(t, field_names, content_map, hidden_list, module_deps, mode="default"):
    cfg_deps = [f"node.type.{t}"] + [f"field.field.node.{t}.{f}" for f in field_names]
    cfg = "\n".join(f"    - {c}" for c in cfg_deps)
    mod = "\n".join(f"    - {m}" for m in module_deps)
    if content_map:
        content = "content:\n" + "\n".join(f"  {k}:\n{v}" for k, v in content_map)
    else:
        content = "content: {  }"
    if hidden_list:
        hidden = "hidden:\n" + "\n".join(f"  {h}: true" for h in hidden_list)
    else:
        hidden = "hidden: {  }"
    FILES[f"core.entity_view_display.node.{t}.{mode}.yml"] = f"""langcode: en
status: true
dependencies:
  config:
{cfg}
  module:
{mod}
id: node.{t}.{mode}
targetEntityType: node
bundle: {t}
mode: {mode}
{content}
{hidden}
"""


def title_widget(w=-5):
    return ("title", f"""    type: string_textfield
    weight: {w}
    region: content
    settings:
      size: 60
      placeholder: ''
    third_party_settings: {{  }}""")


# =====================================================================
# SPLASH PAGE
# =====================================================================
T = "splash_page"
node_type(T, "Splash Page", "Standalone marketing or promotional splash pages with a manual URL alias and optional publish scheduling.")

body_field(T)
field(T, "field_path_alias", "Path Alias", "string", [],
      desc="Manual URL path, e.g. /tv — applied as the node URL alias, overrides pathauto.")
field(T, "field_splash_status", "Status", "list_string", ["options"])
field(T, "field_publish_date", "Publish Date", "datetime", ["datetime"])
field(T, "field_unpublish_date", "Unpublish Date", "datetime", ["datetime"])
field(T, "field_hero_media", "Hero Media", "entity_reference", ["media"],
      extra_settings=er_media_settings())
field(T, "field_hero_headline", "Hero Headline", "string", [])
field(T, "field_hero_subheadline", "Hero Subheadline", "string", [])
field(T, "field_cta_text", "CTA Text", "string", [])
field(T, "field_cta_link", "CTA Link", "link", ["link"], extra_settings=link_settings())
field(T, "field_social_links", "Social Links", "entity_reference_revisions",
      ["paragraphs", "entity_reference_revisions"], extra_settings=err_para_settings("social_link"))
field(T, "field_theme_override", "Theme Override", "list_string", ["options"])
field(T, "field_meta_title", "Meta Title", "string", [])
field(T, "field_meta_description", "Meta Description", "string_long", [])

SP_FIELDS = ["body", "field_path_alias", "field_splash_status", "field_publish_date",
             "field_unpublish_date", "field_hero_media", "field_hero_headline",
             "field_hero_subheadline", "field_cta_text", "field_cta_link",
             "field_social_links", "field_theme_override", "field_meta_title",
             "field_meta_description"]

form_display(T, SP_FIELDS, [
    title_widget(),
    ("body", w_body(0)),
    ("field_path_alias", w_string(1)),
    ("field_splash_status", w_options(2)),
    ("field_publish_date", w_datetime(3)),
    ("field_unpublish_date", w_datetime(4)),
    ("field_hero_media", w_er_autocomplete(5)),
    ("field_hero_headline", w_string(6)),
    ("field_hero_subheadline", w_string(7)),
    ("field_cta_text", w_string(8)),
    ("field_cta_link", w_link(9)),
    ("field_social_links", w_paragraphs(10)),
    ("field_theme_override", w_options(11)),
    ("field_meta_title", w_string(12)),
    ("field_meta_description", w_textarea_string(13)),
], ["datetime", "link", "options", "text", "media", "paragraphs"])

view_display(T, SP_FIELDS, [
    ("body", f_text(0)),
    ("field_path_alias", f_string(1)),
    ("field_splash_status", f_list(2)),
    ("field_publish_date", f_datetime(3)),
    ("field_unpublish_date", f_datetime(4)),
    ("field_hero_media", f_er_media_view(5)),
    ("field_hero_headline", f_string(6)),
    ("field_hero_subheadline", f_string(7)),
    ("field_cta_text", f_string(8)),
    ("field_cta_link", f_link(9)),
    ("field_social_links", f_err_view(10)),
    ("field_theme_override", f_list(11)),
    ("field_meta_title", f_string(12)),
    ("field_meta_description", f_basic_string(13)),
], [], ["datetime", "entity_reference_revisions", "link", "options", "text"])

# teaser: summary body, everything else hidden
teaser_body = """    type: text_summary_or_trimmed
    label: hidden
    settings:
      trim_length: 600
    third_party_settings: {  }
    weight: 0
    region: content"""
view_display(T, SP_FIELDS, [("body", teaser_body)],
             [f for f in SP_FIELDS if f != "body"],
             ["text"], mode="teaser")

# =====================================================================
# HOMEPAGE
# =====================================================================
T = "homepage"
node_type(T, "Homepage", "The FAMtastic Designs homepage with hero, why, process, service area, featured content, and final CTA sections.")

field(T, "field_hero_headline", "Hero Headline", "string", [])
field(T, "field_hero_subheadline", "Hero Subheadline", "string", [])
field(T, "field_cta_primary_text", "CTA Primary Text", "string", [])
field(T, "field_cta_primary_link", "CTA Primary Link", "link", ["link"], extra_settings=link_settings())
field(T, "field_cta_secondary_text", "CTA Secondary Text", "string", [])
field(T, "field_cta_secondary_link", "CTA Secondary Link", "link", ["link"], extra_settings=link_settings())
field(T, "field_why_title", "Why Section Title", "string", [])
field(T, "field_why_items", "Why Items", "entity_reference_revisions",
      ["paragraphs", "entity_reference_revisions"], extra_settings=err_para_settings("why_item"))
field(T, "field_process_title", "Process Section Title", "string", [])
field(T, "field_process_steps", "Process Steps", "entity_reference_revisions",
      ["paragraphs", "entity_reference_revisions"], extra_settings=err_para_settings("process_step"))
field(T, "field_service_area_title", "Service Area Title", "string", [])
field(T, "field_service_area_cities", "Service Area Cities", "string_long", [])
field(T, "field_final_cta_title", "Final CTA Title", "string", [])
field(T, "field_final_cta_body", "Final CTA Body", "text_long", ["text"])
field(T, "field_featured_services", "Featured Services", "entity_reference", ["node"],
      extra_settings=er_node_settings("service_page"))
field(T, "field_featured_case_studies", "Featured Case Studies", "entity_reference", ["node"],
      extra_settings=er_node_settings("case_study"))
field(T, "field_featured_testimonials", "Featured Testimonials", "entity_reference", ["node"],
      extra_settings=er_node_settings("testimonial"))

HP_FIELDS = ["field_hero_headline", "field_hero_subheadline", "field_cta_primary_text",
             "field_cta_primary_link", "field_cta_secondary_text", "field_cta_secondary_link",
             "field_why_title", "field_why_items", "field_process_title", "field_process_steps",
             "field_service_area_title", "field_service_area_cities", "field_final_cta_title",
             "field_final_cta_body", "field_featured_services", "field_featured_case_studies",
             "field_featured_testimonials"]

form_display(T, HP_FIELDS, [
    title_widget(),
    ("field_hero_headline", w_string(0)),
    ("field_hero_subheadline", w_string(1)),
    ("field_cta_primary_text", w_string(2)),
    ("field_cta_primary_link", w_link(3)),
    ("field_cta_secondary_text", w_string(4)),
    ("field_cta_secondary_link", w_link(5)),
    ("field_why_title", w_string(6)),
    ("field_why_items", w_paragraphs(7)),
    ("field_process_title", w_string(8)),
    ("field_process_steps", w_paragraphs(9)),
    ("field_service_area_title", w_string(10)),
    ("field_service_area_cities", w_textarea_string(11)),
    ("field_final_cta_title", w_string(12)),
    ("field_final_cta_body", w_textarea_text(13)),
    ("field_featured_services", w_er_autocomplete(14)),
    ("field_featured_case_studies", w_er_autocomplete(15)),
    ("field_featured_testimonials", w_er_autocomplete(16)),
], ["link", "text", "paragraphs"])

view_display(T, HP_FIELDS, [
    ("field_hero_headline", f_string(0)),
    ("field_hero_subheadline", f_string(1)),
    ("field_cta_primary_text", f_string(2)),
    ("field_cta_primary_link", f_link(3)),
    ("field_cta_secondary_text", f_string(4)),
    ("field_cta_secondary_link", f_link(5)),
    ("field_why_title", f_string(6)),
    ("field_why_items", f_err_view(7)),
    ("field_process_title", f_string(8)),
    ("field_process_steps", f_err_view(9)),
    ("field_service_area_title", f_string(10)),
    ("field_service_area_cities", f_basic_string(11)),
    ("field_final_cta_title", f_string(12)),
    ("field_final_cta_body", f_text(13)),
    ("field_featured_services", f_er_label(14)),
    ("field_featured_case_studies", f_er_label(15)),
    ("field_featured_testimonials", f_er_label(16)),
], [], ["entity_reference_revisions", "link", "text"])

view_display(T, HP_FIELDS, [], HP_FIELDS, [], mode="teaser")


def main():
    if "--validate" in sys.argv:
        import yaml
        ok, bad = 0, []
        for name in sorted(FILES):
            path = os.path.join(OUT, name)
            if not os.path.isfile(path):
                bad.append((name, "MISSING"))
                continue
            try:
                d = yaml.safe_load(open(path))
                assert d.get("langcode") == "en" and d.get("status") is True, "langcode/status"
                assert "uuid" not in d and "_core" not in d, "uuid/_core present"
                ok += 1
            except Exception as e:
                bad.append((name, e))
        print(f"Validated {ok}/{len(FILES)} files OK")
        for name, e in bad:
            print("BAD:", name, e)
        sys.exit(1 if bad else 0)

    for name, content in FILES.items():
        with open(os.path.join(OUT, name), "w") as fh:
            fh.write(content)
    print(f"Wrote {len(FILES)} files to {OUT}")


if __name__ == "__main__":
    main()
