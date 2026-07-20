#!/usr/bin/env python3
"""Generator for FAMtastic Designs v2 — simple content types config.
Generates node types (page, blog_post, faq_item, testimonial) field instances
and form/view displays into config/site/. Storages are created by the
foundation agent — this script only references them."""
import os
import yaml

OUT = os.path.dirname(os.path.abspath(__file__))


class D11Dumper(yaml.Dumper):
    def increase_indent(self, flow=False, indentless=False):
        return super().increase_indent(flow, False)


def dump(data, filename):
    path = os.path.join(OUT, filename)
    with open(path, "w") as f:
        yaml.dump(data, f, Dumper=D11Dumper, default_flow_style=False,
                  sort_keys=False, allow_unicode=True, width=1000)
    return filename


# ---------------------------------------------------------------- field defs
# kind -> (field_type, module_dep, settings, widget, widget_settings,
#          formatter, formatter_settings)
KINDS = {
    "body": dict(
        field_type="text_with_summary", module="text",
        settings=dict(display_summary=True, required_summary=False,
                      allowed_formats={}),
        widget="text_textarea_with_summary",
        widget_settings=dict(rows=9, summary_rows=3, placeholder="",
                             show_summary=False),
        formatter="text_default", formatter_settings={},
        teaser_formatter="text_summary_or_trimmed",
        teaser_settings=dict(trim_length=600),
    ),
    "string": dict(
        field_type="string", module=None, settings={},
        widget="string_textfield",
        widget_settings=dict(size=60, placeholder=""),
        formatter="string", formatter_settings={},
        teaser_formatter="string", teaser_settings={},
    ),
    "string_long": dict(
        field_type="string_long", module=None, settings={},
        widget="string_textarea",
        widget_settings=dict(rows=5, placeholder=""),
        formatter="basic_string", formatter_settings={},
        teaser_formatter="basic_string", teaser_settings={},
    ),
    "text_long": dict(
        field_type="text_long", module="text", settings={},
        widget="text_textarea",
        widget_settings=dict(rows=5, placeholder=""),
        formatter="text_default", formatter_settings={},
        teaser_formatter="text_default", teaser_settings={},
    ),
    "integer": dict(
        field_type="integer", module=None, settings={},
        widget="number", widget_settings=dict(placeholder=""),
        formatter="number_integer",
        formatter_settings=dict(thousand_separator="", prefix_suffix=False),
        teaser_formatter="number_integer",
        teaser_settings=dict(thousand_separator="", prefix_suffix=False),
    ),
    "link": dict(
        field_type="link", module="link",
        settings=dict(link_type=17, title=2),
        widget="link_default",
        widget_settings=dict(placeholder_url="", placeholder_title=""),
        formatter="link_default", formatter_settings={},
        teaser_formatter="link_default", teaser_settings={},
    ),
    "image": dict(
        field_type="image", module="image",
        settings=dict(
            handler="default:file", handler_settings={},
            file_directory="[date:custom:Y]-[date:custom:m]",
            file_extensions="png gif jpg jpeg webp",
            max_filesize="", max_resolution="", min_resolution="",
            alt_field=True, alt_field_required=True,
            title_field=False, title_field_required=False,
            default_image=dict(uuid=None, alt="", title="",
                               width=None, height=None),
        ),
        widget="image_image",
        widget_settings=dict(progress_indicator="throbber",
                             preview_image_style="thumbnail"),
        formatter="image",
        formatter_settings=dict(image_style="", image_link=""),
        teaser_formatter="image",
        teaser_settings=dict(image_style="medium", image_link="content"),
    ),
    "datetime_date": dict(
        field_type="datetime", module="datetime",
        settings=dict(datetime_type="date"),
        widget="datetime_default", widget_settings={},
        formatter="datetime_default",
        formatter_settings=dict(format_type="medium", timezone_override=""),
        teaser_formatter="datetime_default",
        teaser_settings=dict(format_type="medium", timezone_override=""),
    ),
    "list_string": dict(
        field_type="list_string", module="options",
        settings=None,  # filled per-field (allowed_values)
        widget="options_select", widget_settings={},
        formatter="list_default", formatter_settings={},
        teaser_formatter="list_default", teaser_settings={},
    ),
}


def er_kind(target_type, bundles=None):
    """entity_reference field factory."""
    if target_type == "user":
        settings = dict(handler="default:user",
                        handler_settings=dict(include_anonymous=True,
                                              filter=dict(type="_none")))
    else:
        bt = {b: b for b in (bundles or [])}
        settings = dict(
            handler="default:" + target_type,
            handler_settings=dict(
                target_bundles=bt or None,
                sort=dict(field="name" if target_type == "taxonomy_term"
                          else "_none",
                          direction="asc" if target_type == "taxonomy_term"
                          else "ASC"),
                auto_create=False, auto_create_bundle=""))
    return dict(
        field_type="entity_reference", module=None,
        settings=settings,
        widget="entity_reference_autocomplete",
        widget_settings=dict(match_operator="CONTAINS", match_limit=10,
                             size=60, placeholder=""),
        formatter="entity_reference_label",
        formatter_settings=dict(link=True),
        teaser_formatter="entity_reference_label",
        teaser_settings=dict(link=True),
        er_target=target_type, er_bundles=bundles or [],
    )


# ------------------------------------------------------------- type registry
def F(name, label, kind, required=False, description="", values=None,
      er=None, cardinality=None):
    return dict(name=name, label=label, kind=kind, required=required,
                description=description, values=values, er=er,
                cardinality=cardinality)

TYPES = {
    "page": dict(
        name="Basic page",
        description="Use <em>basic pages</em> for your static content, such as an 'About us' page.",
        display_submitted=False,
        fields=[
            F("body", "Body", "body"),
            F("field_page_type", "Page Type", "list_string",
              values=["about", "contact", "privacy", "terms", "custom"]),
            F("field_hero_headline", "Hero Headline", "string"),
            F("field_hero_subheadline", "Hero Subheadline", "string"),
            F("field_cta_text", "CTA Text", "string"),
            F("field_cta_link", "CTA Link", "link"),
            F("field_sort_order", "Sort Order", "integer"),
            F("field_meta_title", "Meta Title", "string"),
            F("field_meta_description", "Meta Description", "string_long"),
        ],
        path_widget=True,
    ),
    "blog_post": dict(
        name="Blog Post",
        description="Blog articles and news for the FAMtastic Designs blog.",
        display_submitted=True,
        fields=[
            F("body", "Body", "body"),
            F("field_excerpt", "Excerpt", "string_long"),
            F("field_blog_category", "Category", None,
              er=er_kind("taxonomy_term", ["blog_categories"])),
            F("field_featured_image", "Featured Image", "image"),
            F("field_author", "Author", None, er=er_kind("user")),
            F("field_published_date", "Published Date", "datetime_date"),
            F("field_meta_title", "Meta Title", "string"),
            F("field_meta_description", "Meta Description", "string_long"),
        ],
        path_widget=True,
    ),
    "faq_item": dict(
        name="FAQ Item",
        description="Frequently asked questions. The title field is the question.",
        display_submitted=False,
        title_label="Question",
        fields=[
            F("field_answer", "Answer", "text_long", required=True),
            F("field_faq_category", "Category", None,
              er=er_kind("taxonomy_term", ["faq_categories"])),
            F("field_related_service", "Related Service", None,
              er=er_kind("node", ["service_page"])),
            F("field_sort_order", "Sort Order", "integer"),
            F("field_meta_title", "Meta Title", "string"),
        ],
        path_widget=True,
    ),
    "testimonial": dict(
        name="Testimonial",
        description="Client testimonials. Not publicly viewable; referenced by other content.",
        display_submitted=False,
        fields=[
            F("field_client_name", "Client Name", "string", required=True),
            F("field_client_title", "Client Title", "string"),
            F("field_location", "Location", "string"),
            F("field_quote", "Quote", "text_long", required=True),
            F("field_related_service", "Related Service", None,
              er=er_kind("node", ["service_page"])),
            F("field_related_case_study", "Related Case Study", None,
              er=er_kind("node", ["case_study"])),
            F("field_photo", "Photo", "image"),
            F("field_sort_order", "Sort Order", "integer"),
        ],
        path_widget=False,
    ),
}


def resolve(field):
    """Return merged kind dict for a field."""
    if field["er"]:
        return field["er"]
    k = dict(KINDS[field["kind"]])
    if field["kind"] == "list_string":
        k["settings"] = dict(
            allowed_values=[dict(value=v, label=v.replace("_", " ").title())
                            for v in field["values"]],
            allowed_values_function="")
    return k


def config_deps(field, tname, kind):
    deps = ["field.storage.node." + field["name"], "node.type." + tname]
    if field["er"]:
        for b in field["er"]["er_bundles"]:
            if field["er"]["er_target"] == "taxonomy_term":
                deps.append("taxonomy.vocabulary." + b)
            elif field["er"]["er_target"] == "node":
                deps.append("node.type." + b)
    return deps


def module_deps(field, kind):
    mods = []
    if kind.get("module"):
        mods.append(kind["module"])
    if field["er"]:
        # FieldConfig adds the module providing the target entity type.
        mods.append(field["er"]["er_target"])
    return mods


created = []

for tname, tdef in TYPES.items():
    # ---------------------------------------------------------- node type
    node_type = dict(
        langcode="en", status=True, dependencies={},
        name=tdef["name"], type=tname, description=tdef["description"],
        help=None, new_revision=True, preview_mode=1,
        display_submitted=tdef["display_submitted"],
    )
    created.append(dump(node_type, f"node.type.{tname}.yml"))

    # optional title label override (faq_item -> 'Question')
    if tdef.get("title_label"):
        bfo = dict(
            langcode="en", status=True,
            dependencies=dict(config=["node.type." + tname]),
            id=f"node.{tname}.title", field_name="title", entity_type="node",
            bundle=tname, label=tdef["title_label"], description="",
            required=True, translatable=False, default_value={},
            default_value_callback="", settings={}, field_type="string",
        )
        created.append(
            dump(bfo, f"core.base_field_override.node.{tname}.title.yml"))

    # ----------------------------------------------------- field instances
    resolved = {}
    for field in tdef["fields"]:
        kind = resolve(field)
        resolved[field["name"]] = (field, kind)
        ff = dict(
            langcode="en", status=True,
            dependencies=dict(config=config_deps(field, tname, kind),
                              module=module_deps(field, kind)),
            id=f"node.{tname}.{field['name']}",
            field_name=field["name"], entity_type="node", bundle=tname,
            label=field["label"], description=field["description"],
            required=field["required"], translatable=True,
            default_value={}, default_value_callback="",
            settings=kind["settings"], field_type=kind["field_type"],
        )
        if not ff["dependencies"]["module"]:
            del ff["dependencies"]["module"]
        created.append(
            dump(ff, f"field.field.node.{tname}.{field['name']}.yml"))

    # ------------------------------------------------------- form display
    cfg_deps = ["node.type." + tname]
    cfg_deps += [f"field.field.node.{tname}.{f['name']}"
                 for f in tdef["fields"]]
    if tdef.get("title_label"):
        cfg_deps.insert(0, f"core.base_field_override.node.{tname}.title")
    mod_deps = {"path": None} if tdef["path_widget"] else {}
    for f, k in resolved.values():
        for m in module_deps(f, k):
            mod_deps[m] = None
    content = {}
    weight = 0
    content["title"] = dict(type="string_textfield", weight=-5,
                            region="content",
                            settings=dict(size=60, placeholder=""),
                            third_party_settings={})
    weight = 1
    for fname, (f, k) in resolved.items():
        content[fname] = dict(type=k["widget"], weight=weight,
                              region="content",
                              settings=k["widget_settings"],
                              third_party_settings={})
        weight += 1
    base_weight = 50
    content["created"] = dict(type="datetime_timestamp", weight=base_weight,
                              region="content", settings={},
                              third_party_settings={})
    if tdef["path_widget"]:
        content["path"] = dict(type="path", weight=base_weight + 1,
                               region="content", settings={},
                               third_party_settings={})
        mod_deps["path"] = None
    content["status"] = dict(type="boolean_checkbox", weight=base_weight + 2,
                             region="content",
                             settings=dict(display_label=True),
                             third_party_settings={})
    content["uid"] = dict(type="entity_reference_autocomplete",
                          weight=base_weight + 3, region="content",
                          settings=dict(match_operator="CONTAINS",
                                        match_limit=10, size=60,
                                        placeholder=""),
                          third_party_settings={})
    form = dict(
        langcode="en", status=True,
        dependencies=dict(config=cfg_deps, module=sorted(mod_deps)),
        id=f"node.{tname}.default", targetEntityType="node", bundle=tname,
        mode="default", content=content,
        hidden=dict(promote=True, sticky=True),
    )
    created.append(
        dump(form, f"core.entity_form_display.node.{tname}.default.yml"))

    # ------------------------------------------------------- view displays
    for mode in ("default", "teaser"):
        cfg_deps = ["node.type." + tname]
        if mode == "teaser":
            cfg_deps.append("core.entity_view_mode.node.teaser")
        cfg_deps += [f"field.field.node.{tname}.{f['name']}"
                     for f in tdef["fields"]]
        mod_deps = {"user": None}
        for f, k in resolved.values():
            for m in module_deps(f, k):
                mod_deps[m] = None
        content = {}
        weight = 0
        for fname, (f, k) in resolved.items():
            if mode == "teaser":
                # keep teaser lean: body/excerpt-style content only
                if fname not in ("body", "field_excerpt", "field_quote",
                                 "field_featured_image", "field_photo"):
                    continue
                fmt, fset = k["teaser_formatter"], k["teaser_settings"]
            else:
                fmt, fset = k["formatter"], k["formatter_settings"]
            content[fname] = dict(
                type=fmt, label="hidden" if fname == "body" else "above",
                settings=fset, third_party_settings={},
                weight=weight, region="content")
            weight += 1
        content["links"] = dict(weight=weight, region="content")
        view = dict(
            langcode="en", status=True,
            dependencies=dict(config=cfg_deps, module=sorted(mod_deps)),
            id=f"node.{tname}.{mode}", targetEntityType="node",
            bundle=tname, mode=mode, content=content, hidden={},
        )
        created.append(dump(
            view, f"core.entity_view_display.node.{tname}.{mode}.yml"))

# ------------------------------------------------------------- validation
errors = []
for fn in created:
    with open(os.path.join(OUT, fn)) as fh:
        try:
            yaml.safe_load(fh)
        except Exception as e:
            errors.append(f"{fn}: {e}")
if errors:
    print("YAML ERRORS:")
    print("\n".join(errors))
else:
    print(f"OK — {len(created)} files validated with yaml.safe_load:")
    for fn in created:
        print(" -", fn)
