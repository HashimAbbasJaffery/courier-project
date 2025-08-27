<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use iio\libmergepdf\Merger;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Webklex\PDFMerger\Facades\PDFMergerFacade as PDFMerger;

class MergeSlipsController extends Controller
{
     public function __invoke()
{
    // --- pick shipments: selected ids (if any) or all ---
    $idsParam = request('ids');
    $query = Shipment::query();

    if ($idsParam) {
        $ids = array_values(array_filter(array_map('intval', explode(',', $idsParam))));
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }
    }

    $shipments = $query->get();

    // --- merge PDFs ---
    $merger = PDFMerger::init();
    foreach ($shipments as $shipment) {
        // files live on "local" (private) disk; slip_link is relative path (e.g. shipments/abc.pdf)
        $merger->addPDF(Storage::disk('local')->path($shipment->slip_link), "all");
    }

    $tmpMerged = tempnam(sys_get_temp_dir(), 'merged_').'.pdf';
    $merger->merge();
    $merger->save($tmpMerged);

    // --- re-layout to 8 x 4.5 in, with your vertical nudge (yOffset = 3.3) ---
    $pageW = 8.0; $pageH = 4.5;
    $pdf = new Fpdi('L', 'in', [$pageW, $pageH]);
    $count = $pdf->setSourceFile($tmpMerged);

    for ($i = 1; $i <= $count; $i++) {
        $tplId = $pdf->importPage($i);
        $pdf->AddPage('L', [$pageW, $pageH]);
        $this->placeTemplate($pdf, $tplId, $pageW, $pageH, 'cover', 0.0, 3.3);
    }

    @unlink($tmpMerged);

    $day = today()->day;
    $month = today()->format('M');

    $filename = "Lep_Awb_" . $day . "_" . $month ;

    return response($pdf->Output('S'))
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', "inline; filename='$filename.pdf'");
}


    function placeTemplate(
    Fpdi $pdf,
    $tplId,
    float $pageW,
    float $pageH,
    string $mode = 'fit',
    float $margin = 0.0,
    float $yOffset = 0.0   // 👈 extra downward shift in inches
) {
    $tpl = $pdf->getTemplateSize($tplId);

    $availW = max(0.0, $pageW - 2*$margin);
    $availH = max(0.0, $pageH - 2*$margin);

    if ($mode === 'stretch') {
        $w = $availW;
        $h = $availH;
    } else {
        $scale = ($mode === 'cover')
            ? max($availW / $tpl['width'],  $availH / $tpl['height'])
            : min($availW / $tpl['width'],  $availH / $tpl['height']);
        $w = $tpl['width'] * $scale;
        $h = $tpl['height'] * $scale;
    }

    // Centering
    $x = $margin + ($availW - $w) / 2.0;
    $y = $margin + ($availH - $h) / 2.0;

    // Apply extra downward offset
    $y += $yOffset;

    $pdf->useTemplate($tplId, $x, $y, $w, $h);
}
}
