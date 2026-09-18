{{--
    Shared LLCC footer bar for all official PDF printouts. Plain CSS (two
    flat bars — deep blue over gold, no diagonal), so the footer text stays
    crisp and selectable rather than rasterized into an image.

    Pinned with position:fixed — DomPDF deliberately supports fixed
    positioning for repeating footers (rendered at the page bottom on
    EVERY page from a single occurrence, so include this partial exactly
    once per document, never inside a per-page/per-batch loop). There is
    no flex parent anywhere in the PDF views; the old margin-top:auto was
    a no-op that left the footer floating after content.
--}}
<div style="position:fixed; bottom:0; left:0; right:0;">
    <div style="height:6px; background:#00339A;"></div>
    <div style="height:10px; background:#FFC000;"></div>
    <div style="text-align:center; font-size:7.5px; color:#555; padding:4px 0;">
        Website: www.llcc.edu.ph &mdash; Fb page: LLCC Public Information Office &mdash; Email: llccadmin@llcc.edu.ph
    </div>
</div>
