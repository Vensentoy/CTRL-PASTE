<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * pdf-forms.md §2 — the official Daily Accomplishment Report printout is
 * NOT one date per document. It batches five consecutive dates per
 * printout, each date getting its own mini-table and per-date
 * "TOTAL HOURS" row, followed by one grand "TOTAL DAILY HOURS" for the
 * whole printout.
 *
 * The database still stores one row per calendar date (data-model.md) —
 * this class is purely a PDF-rendering concern: it chunks an ordered set
 * of DAR rows into printable batches of five, so a date range longer
 * than five entries renders as multiple consecutive printout pages, each
 * with its own subtotal/grand-total, rather than one long unbounded
 * table. Don't conflate this with the storage model.
 */
class DarPdfGrouper
{
    /**
     * @param  Collection  $dars  DAR rows, expected pre-sorted by report_date ascending.
     * @return Collection  Collection of batches; each batch is itself a
     *                      Collection of up to 5 DAR rows, in printout order.
     */
    public function batch(Collection $dars): Collection
    {
        return $dars->values()->chunk(5);
    }

    /**
     * Grand "TOTAL DAILY HOURS" for one printout batch (pdf-forms.md).
     */
    public function batchTotalHours(Collection $batch): float
    {
        return (float) $batch->sum(fn ($dar) => (float) $dar->hours_rendered);
    }
}
