<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

class PurchaseOrderPdfController extends Controller
{
    public function __invoke(PurchaseOrder $purchaseOrder): Response
    {
        // Only logged-in NADI users can download POs
        abort_unless(auth()->check(), 403);

        $po = $purchaseOrder->load(['supplier', 'items.sku.product', 'creator', 'approver']);

        $html = view('pdf.purchase-order', [
            'po'   => $po,
            'logo' => $this->logoDataUri(),
        ])->render();

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $po->po_no . '.pdf"',
        ]);
    }

    /**
     * Embed the logo directly in the PDF (no file paths for dompdf to resolve).
     */
    protected function logoDataUri(): ?string
    {
        $path = public_path(config('nadi.po_logo'));

        if (! is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }
}