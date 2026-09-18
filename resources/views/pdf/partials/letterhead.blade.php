{{--
    Shared LLCC letterhead for all official PDF printouts (DAR / WAR / MAR /
    Information Sheet). The banner (blue/gold wave + crest + college name +
    address + school code) is a static 300-DPI image because the diagonal
    wave is a freeform vector shape DomPDF cannot reproduce from CSS/SVG —
    and the banner text never varies by student, form, or date, so baking
    it is pixel-accurate, not a shortcut. Do NOT hand-code the wave in CSS.

    DomPDF needs a local filesystem path (remote fetching is off — there
    is no config/dompdf.php enabling it). If this renders blank, switch to
    base64 embedding:
    data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/pdf/letterhead-band.png'))) }}
--}}
<img src="{{ public_path('images/pdf/letterhead-band.png') }}" style="width:100%; display:block; margin-bottom:10px;">
