"""Private document worker. Requests and results are JSON; no shell interpolation."""
import copy
import json
import re
import sys
import zipfile
from pathlib import Path
from lxml import etree as ET

W = "http://schemas.openxmlformats.org/wordprocessingml/2006/main"
NS = {"w": W}
PARSER = ET.XMLParser(resolve_entities=False, no_network=True)
PLACEHOLDER = "Write this section here."


def xml(data):
    return ET.fromstring(data, parser=PARSER)


def read_package(path):
    with zipfile.ZipFile(path) as archive:
        if len(archive.infolist()) > 10000 or sum(i.file_size for i in archive.infolist()) > 256 * 1024 * 1024:
            raise ValueError("Document exceeds the expanded size limit.")
        parts = {name: archive.read(name) for name in archive.namelist()}
    if "word/document.xml" not in parts or "[Content_Types].xml" not in parts:
        raise ValueError("A valid DOCX document is required.")
    if any("vbaProject" in name for name in parts):
        raise ValueError("Macros are not allowed.")
    for name, data in parts.items():
        if name.endswith(".rels"):
            for rel in xml(data):
                if rel.get("TargetMode") == "External" and not rel.get("Type", "").endswith("/hyperlink"):
                    raise ValueError("Embed linked images and objects before submitting.")
    return parts


def write_package(parts, root, output):
    parts["word/document.xml"] = ET.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)
    with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED) as archive:
        for name, data in parts.items():
            archive.writestr(name, data)


def element(name, **attrs):
    node = ET.Element("{%s}%s" % (W, name))
    for key, value in attrs.items():
        node.set("{%s}%s" % (W, key), str(value))
    return node


def paragraph(text, style=None):
    p = element("p")
    if style:
        props = element("pPr")
        props.append(element("pStyle", val=style))
        p.append(props)
    run = element("r")
    node = element("t")
    node.text = text
    run.append(node)
    p.append(run)
    return p


def text(node):
    return "".join(node.xpath(".//w:t/text()", namespaces=NS))


def controls(root):
    result = {}
    for sdt in root.xpath(".//w:sdt", namespaces=NS):
        tags = sdt.xpath("./w:sdtPr/w:tag/@w:val", namespaces=NS)
        if tags and tags[0].startswith("eprism:"):
            result.setdefault(tags[0][7:], []).append(sdt)
    return result


def section_control(section):
    sdt = element("sdt")
    props = element("sdtPr")
    props.append(element("tag", val="eprism:" + section["key"]))
    props.append(element("alias", val=section["label"]))
    props.append(element("lock", val="sdtLocked"))
    sdt.append(props)
    body = element("sdtContent")
    body.append(paragraph(section["label"], "Heading1"))
    body.append(paragraph(PLACEHOLDER))
    sdt.append(body)
    return sdt


def seed(args):
    parts = read_package(args["input"])
    root = xml(parts["word/document.xml"])
    body = root.find("w:body", NS)
    existing = controls(root)
    for section in args["sections"]:
        if section["key"] in existing:
            continue
        control = section_control(section)
        token = "${section:" + section["key"] + "}"
        anchor = next((p for p in body.findall("w:p", NS) if text(p).strip() == token), None)
        if anchor is not None:
            body.replace(anchor, control)
        else:
            sect = body.find("w:sectPr", NS)
            body.insert(list(body).index(sect) if sect is not None else len(body), control)
    # Metadata remains read-only; section controls alone are editable.
    for index, node in enumerate(list(body)):
        if node.tag in ("{%s}sectPr" % W, "{%s}sdt" % W):
            continue
        wrapper = element("sdt")
        props = element("sdtPr")
        props.append(element("tag", val="eprism-metadata:" + str(index)))
        props.append(element("lock", val="sdtContentLocked"))
        wrapper.append(props)
        content = element("sdtContent")
        position = list(body).index(node)
        body.remove(node)
        content.append(node)
        wrapper.append(content)
        body.insert(position, wrapper)
    write_package(parts, root, args["output"])
    return {"ok": True}


def migrate(args):
    # docxcompose remaps images, numbering and relationships before tagged sections move.
    from docx import Document
    from docxcompose.composer import Composer
    composer = Composer(Document(args["input"]))
    target_body = composer.doc.element.body
    original_count = len(target_body)
    composer.append(Document(args["parent"]))
    composer.save(args["output"])
    parts = read_package(args["output"])
    root = xml(parts["word/document.xml"])
    target_body = root.find("w:body", NS)
    original = list(target_body)[:original_count - 1]
    source = list(target_body)[original_count - 1:-1]
    old = {}
    for node in source:
        for key, values in controls(node).items():
            old.update({key: values[0]})
        if node.tag == "{%s}sdt" % W:
            tags = node.xpath("./w:sdtPr/w:tag/@w:val", namespaces=NS)
            if tags and tags[0].startswith("eprism:"):
                old[tags[0][7:]] = node
    # Maps a completed-template section key to the proposal-template key holding the same
    # content, wherever SubmissionTemplateRegistry renamed it across the two templates for the
    # same research_type (action_proposal -> action_completed, basic_proposal ->
    # basic_completed) — every other rich_text key is identical between the two, so only
    # genuine renames need an entry here.
    aliases = {"ethical_issues": "ethical_considerations", "introduction_and_rationale": "context_and_rationale"}
    for node in original:
        if node.tag != "{%s}sdt" % W:
            continue
        tags = node.xpath("./w:sdtPr/w:tag/@w:val", namespaces=NS)
        key = tags[0][7:] if tags else ""
        previous = old.get(key)
        if previous is None:
            previous = old.get(aliases.get(key, ""))
        if previous is not None:
            destination = node.find("w:sdtContent", NS)
            previous_content = previous.find("w:sdtContent", NS)
            for child in list(destination)[1:]:
                destination.remove(child)
            for child in list(previous_content)[1:]:
                destination.append(copy.deepcopy(child))
    for node in source:
        if node.getparent() is target_body:
            target_body.remove(node)
    write_package(parts, root, args["output"])
    return {"ok": True}


def defaults(parts, kind):
    if "word/styles.xml" not in parts:
        return {}
    styles = xml(parts["word/styles.xml"])
    node = styles.find("w:docDefaults/w:" + kind + "Default/w:" + kind, NS)
    return {ET.QName(child).localname: dict(child.attrib) for child in node} if node is not None else {}


def fonts(attributes, parts):
    a = "http://schemas.openxmlformats.org/drawingml/2006/main"
    theme = xml(parts["word/theme/theme1.xml"]) if "word/theme/theme1.xml" in parts else None
    result = {}
    for face in ("ascii", "hAnsi"):
        literal = attributes.get("{%s}%s" % (W, face))
        themed = attributes.get("{%s}%sTheme" % (W, face))
        if literal:
            result[face] = literal.casefold()
        elif themed and theme is not None:
            branch = "majorFont" if themed.startswith("major") else "minorFont"
            latin = theme.find(".//{%s}%s/{%s}latin" % (a, branch, a))
            if latin is not None:
                result[face] = latin.get("typeface", "").casefold()
    return result


def properties(style, styles, kind, seen=None):
    if style is None:
        return {}
    seen = set() if seen is None else seen
    key = style.get("{%s}styleId" % W)
    if key in seen:
        return {}
    seen.add(key)
    parent = style.find("w:basedOn", NS)
    result = properties(styles.get(parent.get("{%s}val" % W)), styles, kind, seen) if parent is not None else {}
    props = style.find("w:" + kind, NS)
    if props is not None:
        for child in props:
            result.setdefault(ET.QName(child).localname, {}).update(child.attrib)
    return result


def validate(args):
    parts = read_package(args["input"])
    baseline = read_package(args["template"])
    root, template = xml(parts["word/document.xml"]), xml(baseline["word/document.xml"])
    found = controls(root)
    errors, warnings, sections = [], [], {}
    for definition in args["sections"]:
        key = definition["key"]
        matches = found.get(key, [])
        if len(matches) != 1:
            errors.append(definition["label"] + ": required section marker is missing or duplicated.")
            sections[key] = ""
            continue
        body = matches[0].find("w:sdtContent", NS)
        children = list(body) if body is not None else []
        if not children or text(children[0]).strip() != definition["label"]:
            errors.append(definition["label"] + ": restore the required heading.")
        value = " ".join(text(node) for node in children[1:]).replace(PLACEHOLDER, "").strip()
        has_image = any(node.xpath(".//w:drawing", namespaces=NS) for node in children[1:])
        sections[key] = value or ("[Image]" if has_image else "")
        if definition.get("required", True) and not sections[key]:
            errors.append(definition["label"] + ": enter section content.")
    for metadata in template.xpath("//w:sdt[w:sdtPr/w:tag[starts-with(@w:val, 'eprism-metadata:')]]", namespaces=NS):
        tag = metadata.xpath("./w:sdtPr/w:tag/@w:val", namespaces=NS)[0]
        matches = root.xpath("//w:sdt[w:sdtPr/w:tag/@w:val=$tag]", namespaces=NS, tag=tag)
        if len(matches) != 1 or text(matches[0]) != text(metadata):
            errors.append("Restore the research metadata from the selected template.")
    all_text = "".join(root.xpath("//w:t/text()", namespaces=NS))
    if re.search(r"\$\{[^}]+\}", all_text):
        errors.append("Replace all unresolved template placeholders.")
    if root.xpath("//w:ins | //w:del", namespaces=NS):
        errors.append("Accept or reject tracked changes before submission.")
    for name in ("pgSz", "pgMar"):
        expected = template.xpath("//w:sectPr/w:" + name, namespaces=NS)
        actual = root.xpath("//w:sectPr/w:" + name, namespaces=NS)
        if expected:
            keys = ("w", "h", "orient") if name == "pgSz" else ("top", "right", "bottom", "left", "header", "footer", "gutter")
            fallback = {"orient": "portrait", "gutter": "0"}
            expected_attrs = {key: expected[-1].get("{%s}%s" % (W, key), fallback.get(key)) for key in keys}
            if not actual or any(any(node.get("{%s}%s" % (W, key), fallback.get(key)) != value for key, value in expected_attrs.items()) for node in actual):
                errors.append("Page size or margins differ from the selected template (" + name + ").")
    if "word/styles.xml" in baseline and "word/styles.xml" in parts:
        original_styles = {s.get("{%s}styleId" % W): s for s in xml(baseline["word/styles.xml"]).findall("w:style", NS)}
        current_styles = {s.get("{%s}styleId" % W): s for s in xml(parts["word/styles.xml"]).findall("w:style", NS)}
        for key, matches in found.items():
            for control in matches:
                for p in control.xpath(".//w:p", namespaces=NS):
                    if not text(p).strip():
                        continue
                    ids = p.xpath("./w:pPr/w:pStyle/@w:val", namespaces=NS)
                    style_id = ids[0] if ids else "Normal"
                    if style_id not in original_styles:
                        warnings.append(key + ": custom style " + style_id + " requires visual review.")
                        continue
                    for kind, checks in (("pPr", ("spacing",)), ("rPr", ("rFonts", "sz"))):
                        expected = defaults(baseline, kind)
                        current = defaults(parts, kind)
                        for name, attrs in properties(original_styles.get(style_id), original_styles, kind).items():
                            expected.setdefault(name, {}).update(attrs)
                        for name, attrs in properties(current_styles.get(style_id), current_styles, kind).items():
                            current.setdefault(name, {}).update(attrs)
                        direct = p.find("w:pPr", NS) if kind == "pPr" else None
                        if direct is not None:
                            for child in direct:
                                current.setdefault(ET.QName(child).localname, {}).update(child.attrib)
                        compared = tuple(prop for prop in checks if prop != "rFonts")
                        font_mismatch = kind == "rPr" and fonts(expected.get("rFonts", {}), baseline) != fonts(current.get("rFonts", {}), parts)
                        if font_mismatch or any(expected.get(prop) and any(current.get(prop, {}).get(k) != v for k, v in expected[prop].items()) for prop in compared):
                            errors.append(key + ": restore template font, size or line spacing for " + style_id + ".")
                        if kind == "rPr":
                            for rpr in p.xpath("./w:r/w:rPr", namespaces=NS):
                                for prop in checks:
                                    override = rpr.find("w:" + prop, NS)
                                    if override is not None and expected.get(prop):
                                        changed = any(expected[prop].get(k) != v for k, v in override.attrib.items() if k in expected[prop])
                                        if prop == "rFonts":
                                            combined = dict(current.get(prop, {}))
                                            combined.update(override.attrib)
                                            changed = fonts(combined, parts) != fonts(expected[prop], baseline)
                                        if changed:
                                            errors.append(key + ": direct font or size overrides differ from the template.")
    return {"valid": not errors, "errors": list(dict.fromkeys(errors)), "warnings": list(dict.fromkeys(warnings)),
            "sections": sections, "text": "\n\n".join(sections.values()), "validator": "eprism-docx-1"}


CHAPTER_TOKEN_RE = re.compile(r"^\$\{([A-Za-z0-9_]+)\}$")


def chapter_token(key):
    return "${" + key + "}"


def chapter_marker(key):
    # Tiny white text carrying the same [[section:<key>]] convention every other engine's
    # rendered PDF already uses (see SubmissionHtmlTemplateRenderer::buildScalars() and the
    # now-retired SubmissionDocxPdfMerger::stampChapterMarker()) — inserted as a genuine text
    # run here so pdf-review.js's wireChapterNav() keeps finding it via ordinary PDF text
    # extraction, unchanged, regardless of which pipeline produced the page.
    p = element("p")
    run = element("r")
    rpr = element("rPr")
    rpr.append(element("sz", val=2))
    rpr.append(element("color", val="FFFFFF"))
    run.append(rpr)
    node = element("t")
    node.text = "[[section:" + key + "]]"
    run.append(node)
    p.append(run)
    return p


def strip_direct_typography(document):
    # Mirrors DocxStyleNormalizer::stripTypographyOverrides() exactly (same tags, same
    # pPr-direct-child guard for jc/spacing) — kept as the one place chapter typography is
    # normalized now that splicing happens here, so there is no separate PHP-side pass copying
    # the admin template's whole styles.xml over the chapter's own (see assemble_chapters()'s
    # own doc comment for why that wholesale copy was dropped).
    body = document.element.body
    for tag in ("rFonts", "sz", "szCs"):
        for node in list(body.iter("{%s}%s" % (W, tag))):
            node.getparent().remove(node)
    for tag in ("jc", "spacing"):
        for node in list(body.iter("{%s}%s" % (W, tag))):
            parent = node.getparent()
            if parent is not None and parent.tag == "{%s}pPr" % W:
                parent.remove(node)


def assemble_chapters(args):
    """
    Splices each rich_text chapter's own .docx body into the admin's already scalar/each-filled
    front-matter template, at that chapter's own `${<key>}` placeholder paragraph — replacing
    the old design where a chapter was never inserted into the template at all, only converted
    and appended as its own separate PDF page (see SubmissionDocxPdfMerger, now retired for the
    per-chapter engine). Uses docxcompose.Composer.insert() (not append()) at the placeholder's
    own body index, which is docxcompose's own supported arbitrary-position API — it already
    handles cross-package image/diagram/shape/footnote relationship remapping (de-duplicating
    identical images by content hash), style merging by *name* (so a chapter's own named style
    reuses the template's style with that name, and a genuinely different style is copied in
    rather than clobbered — see its class-level review below), and numbering remapping with a
    fresh restart per chapter for the first list of each style it encounters.

    Deliberately does NOT copy the template's whole styles.xml over the chapter first (the old
    DocxStyleNormalizer::normalize() behavior) — that would discard any chapter-specific named
    style (or a built-in list/table style) the template itself doesn't happen to define, which
    is exactly the "destroys chapter-specific structures" failure mode docxcompose's own
    per-name merge avoids. Only *direct* run/paragraph typography overrides (rFonts/sz/szCs/
    jc/spacing) are stripped from the chapter first, so its headings/body text fall through to
    whatever the template's own same-named style resolves to, without losing any style the
    template doesn't define at all.

    Validation is deliberately strict and named-by-key (matches DocxTemplateFiller's own
    "errors loud, name the key" convention): a chapter with content but no matching placeholder,
    more than one matching placeholder, a placeholder found inline or inside a table cell
    instead of its own standalone paragraph, or a placeholder-shaped paragraph left over that
    isn't one of the chapters this call was told about, all raise a specific, actionable error
    rather than silently appending elsewhere or leaving a literal "${...}" in the manuscript.

    @param args: {"template": path to the filled front-matter docx, "chapters": [{"key": str,
                  "path": path to that chapter's own docx, or None if never written}, ...],
                  "output": path to save the assembled docx to}
    """
    from docx import Document
    from docx.oxml.ns import qn
    from docx.text.paragraph import Paragraph
    from docxcompose.composer import Composer

    document = Document(args["template"])
    body = document.element.body
    composer = Composer(document)
    provided_keys = {chapter["key"] for chapter in args["chapters"]}

    for chapter in args["chapters"]:
        key = chapter["key"]
        path = chapter.get("path")
        needle = chapter_token(key)

        matches = [p for p in document.paragraphs if p.text.strip() == needle]

        if not matches:
            if path is None:
                # Nothing written for this chapter yet, and no placeholder to fill either —
                # nothing to do (a required-but-empty chapter is a readiness-time concern, not
                # this operation's).
                continue

            inline = any(needle in p.text and p.text.strip() != needle for p in document.paragraphs)
            in_table = any(
                Paragraph(cell_p, None).text.strip() == needle
                for tbl in body.iter(qn("w:tbl"))
                for cell_p in tbl.iter(qn("w:p"))
            )
            if inline or in_table:
                where = "inside a table cell" if in_table else "inline with other text"
                raise ValueError(
                    "Chapter placeholder " + needle + " for \"" + key + "\" must occupy its own "
                    "paragraph — found " + where + " instead."
                )
            raise ValueError(
                "Document template is missing a placeholder for chapter \"" + key + "\" — add "
                + needle + " as its own paragraph."
            )

        if len(matches) > 1:
            raise ValueError(
                "Document template has " + str(len(matches)) + " placeholders for chapter \""
                + key + "\" (" + needle + ") — it must appear exactly once."
            )

        anchor = matches[0]
        index = list(body).index(anchor._p)

        if path is not None:
            chapter_doc = Document(path)
            strip_direct_typography(chapter_doc)
            body.insert(index, chapter_marker(key))
            composer.insert(index + 1, chapter_doc)

        anchor._p.getparent().remove(anchor._p)

    unknown = sorted(set(
        match.group(1)
        for p in document.paragraphs
        for match in [CHAPTER_TOKEN_RE.match(p.text.strip())]
        if match and match.group(1) not in provided_keys
    ))
    if unknown:
        raise ValueError(
            "Unrecognized chapter placeholder(s) in the document template: "
            + ", ".join(chapter_token(key) for key in unknown) + "."
        )

    document.save(args["output"])
    return {"ok": True}


def pdf(args, encrypt=False):
    import pikepdf
    with pikepdf.open(args["input"]) as document:
        for attachment in args.get("attachments", []):
            with pikepdf.open(attachment) as other:
                document.pages.extend(other.pages)
        options = {}
        if encrypt:
            options["encryption"] = pikepdf.Encryption(
                owner=args["owner_password"], user=args.get("user_password", ""), R=6,
                allow=pikepdf.Permissions(extract=False, modify_annotation=False, modify_assembly=False,
                    modify_form=False, modify_other=False, print_lowres=True, print_highres=True))
        document.save(args["output"], **options)
    return {"ok": True}


if __name__ == "__main__":
    try:
        request = json.load(sys.stdin)
        operation = sys.argv[1]
        handlers = {"seed": seed, "validate": validate, "migrate": migrate,
                    "assemble_chapters": assemble_chapters,
                    "merge_pdf": pdf, "encrypt_pdf": lambda args: pdf(args, True)}
        print(json.dumps(handlers[operation](request)))
    except Exception as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)

