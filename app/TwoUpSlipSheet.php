<?php

namespace App;

use App\Models\Shipment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class TwoUpSlipSheet
{
    public function buildForShipments(int $idA, int $idB): string
    {
        $a = Shipment::findOrFail($idA);
        $b = Shipment::findOrFail($idB);

        if (! $a->slip_link || ! $b->slip_link) {
            abort(422, 'Both shipments must have slip_link PDFs.');
        }

        $pathA = $this->fetchToTemp($a->slip_link);
        $pathB = $this->fetchToTemp($b->slip_link);

        // Create A4 page (portrait). Units: mm.
        $pdf = new Fpdi('P', 'mm', 'A4');
        $pdf->AddPage();

        // Margins and target rectangles (top & bottom halves)
        $margin = 6;                 // small printable margin
        $pageW  = 210 - 2*$margin;   // A4 width = 210mm
        $pageH  = 297 - 2*$margin;   // A4 height = 297mm
        $cellH  = ($pageH - 4) / 2;  // split roughly in half with tiny gutter

        // Place first label (top)
        $this->placePdfFirstPage($pdf, $pathA, $margin, $margin, $pageW, $cellH);

        // Hairline gutter
        $pdf->SetDrawColor(230);
        $pdf->Line($margin, $margin + $cellH + 2, $margin + $pageW, $margin + $cellH + 2);

        // Place second label (bottom)
        $this->placePdfFirstPage($pdf, $pathB, $margin, $margin + $cellH + 4, $pageW, $cellH);

        // Save to storage and return public URL
        $name = 'two-up-'.Str::ulid().'.pdf';
        $diskPath = "slips/{$name}";
        Storage::disk('public')->put($diskPath, $pdf->Output('S'));

        return Storage::disk('public')->path($diskPath);
    }

    private function placePdfFirstPage(Fpdi $pdf, string $src, float $x, float $y, float $maxW, float $maxH): void
    {
        $pageCount = $pdf->setSourceFile($src);
        $tpl = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tpl);

        // scale to fit within (maxW x maxH) preserving aspect ratio
        $scale = min($maxW / $size['width'], $maxH / $size['height']);
        $w = $size['width'] * $scale;
        $h = $size['height'] * $scale;

        // center within the target rect
        $cx = $x + ($maxW - $w) / 2;
        $cy = $y + ($maxH - $h) / 2;

        $pdf->useTemplate($tpl, $cx, $cy, $w, $h, true);
    }

    private function fetchToTemp(string $link): string
    {
        // If it's a public storage path
        if (Storage::disk('public')->exists($link)) {
            return Storage::disk('public')->path($link);
        }

        // Otherwise treat as remote URL
        $res = Http::timeout(20)->get($link);
        abort_unless($res->ok(), 422, "Failed to download slip: {$link}");
        $tmp = tempnam(sys_get_temp_dir(), 'slip_');
        file_put_contents($tmp, $res->body());
        return $tmp;
    }
}
