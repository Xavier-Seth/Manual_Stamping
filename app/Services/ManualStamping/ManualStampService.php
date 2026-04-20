<?php

namespace App\Services\ManualStamping;

use setasign\Fpdi\TcpdfFpdi;

class ManualStampService
{
    public function stampMasterCopy(string $inputPath, string $outputPath): void
    {
        $pdf = new TcpdfFpdi();

        $pageCount = $pdf->setSourceFile($inputPath);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);

            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            $this->drawMasterStamp($pdf, $size['width'], $size['height']);
        }

        $pdf->Output($outputPath, 'F');
    }

    public function stampControlledCopy(string $inputPath, string $outputPath): void
    {
        $pdf = new TcpdfFpdi();

        $pageCount = $pdf->setSourceFile($inputPath);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);

            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            $this->drawControlledCopyMasterStamp($pdf, $size['width'], $size['height']);
            $this->drawControlledCopyStamp($pdf, $size['width'], $size['height']);
        }

        $pdf->Output($outputPath, 'F');
    }

    public function stampUncontrolledCopy(string $inputPath, string $outputPath): void
    {
        $pdf = new TcpdfFpdi();

        $pageCount = $pdf->setSourceFile($inputPath);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);

            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            $this->drawUncontrolledCopyMasterStamp($pdf, $size['width'], $size['height']);
            $this->drawUncontrolledCopyControlledStamp($pdf, $size['width'], $size['height']);
            $this->drawUncontrolledCopyStamp($pdf, $size['width'], $size['height']);
        }

        $pdf->Output($outputPath, 'F');
    }

    private function drawMasterStamp(TcpdfFpdi $pdf, float $pageWidth, float $pageHeight): void
    {
        $layout = $this->masterCopyLayout();

        [$stampX, $stampY] = $this->masterStampCoordinates($pageWidth, $pageHeight);

        $pdf->SetDrawColor(220, 38, 38);
        $pdf->SetTextColor(220, 38, 38);
        $pdf->SetLineWidth(0.35);

        $pdf->Rect($stampX, $stampY, $layout['stamp_w'], $layout['stamp_h']);

        $pdf->SetFont(
            $layout['font_family'],
            $layout['font_style'],
            $layout['font_size']
        );

        $master = 'MASTER COPY';
        $lnu = 'LNU';

        $masterWidth = $pdf->GetStringWidth($master);
        $lnuWidth = $pdf->GetStringWidth($lnu);

        $textCenterX = $stampX + ($layout['stamp_w'] / 2);

        // text controls
        $textOffsetX = 0.0;   // move both lines left/right
        $blockOffsetY = -2.0; // move both lines up/down
        $lineGap = 5.0;       // spacing between MASTER COPY and LNU
        $lnuNudgeX = 0.0;     // move only LNU left/right

        $startY = $stampY + ($layout['stamp_h'] / 2) - ($lineGap / 2) + $blockOffsetY;

        $pdf->Text(
            $textCenterX - ($masterWidth / 2) + $textOffsetX,
            $startY,
            $master
        );

        $pdf->Text(
            $textCenterX - ($lnuWidth / 2) + $textOffsetX + $lnuNudgeX,
            $startY + $lineGap,
            $lnu
        );
    }

    private function drawControlledCopyMasterStamp(TcpdfFpdi $pdf, float $pageWidth, float $pageHeight): void
    {
        $this->drawMasterStampVariant($pdf, $pageWidth, $pageHeight, [0, 0, 0]);
    }

    private function drawControlledCopyStamp(TcpdfFpdi $pdf, float $pageWidth, float $pageHeight): void
    {
        $this->drawControlledStampVariant($pdf, $pageWidth, $pageHeight, [220, 38, 38]);
    }

    private function drawUncontrolledCopyMasterStamp(TcpdfFpdi $pdf, float $pageWidth, float $pageHeight): void
    {
        $this->drawMasterStampVariant($pdf, $pageWidth, $pageHeight, [0, 0, 0]);
    }

    private function drawUncontrolledCopyControlledStamp(TcpdfFpdi $pdf, float $pageWidth, float $pageHeight): void
    {
        $this->drawControlledStampVariant($pdf, $pageWidth, $pageHeight, [0, 0, 0]);
    }

    private function drawUncontrolledCopyStamp(TcpdfFpdi $pdf, float $pageWidth, float $pageHeight): void
    {
        $layout = $this->uncontrolledCopyLayout();

        [$stampX, $stampY] = $this->uncontrolledStampCoordinates($pageWidth, $pageHeight);

        $this->drawTwoLineStamp(
            $pdf,
            $stampX,
            $stampY,
            $layout['stamp_w'],
            $layout['stamp_h'],
            [
                'line_1' => 'UNCONTROLLED COPY',
                'line_2' => 'LNU',
                'color' => [220, 38, 38],
                'font_family' => $layout['font_family'],
                'font_style' => $layout['font_style'],
                'font_size' => $layout['font_size'],
                'line_1_font_size' => $layout['line_1_font_size'],
                'line_2_font_size' => $layout['line_2_font_size'],
                'text_offset_x' => $layout['text_offset_x'],
                'block_offset_y' => $layout['block_offset_y'],
                'line_gap' => $layout['line_gap'],
                'line_2_offset_x' => $layout['line_2_offset_x'],
            ]
        );
    }

    private function drawMasterStampVariant(
        TcpdfFpdi $pdf,
        float $pageWidth,
        float $pageHeight,
        array $color
    ): void {
        $layout = $this->masterCopyLayout();

        [$stampX, $stampY] = $this->masterStampCoordinates($pageWidth, $pageHeight);

        $this->drawTwoLineStamp(
            $pdf,
            $stampX,
            $stampY,
            $layout['stamp_w'],
            $layout['stamp_h'],
            [
                'line_1' => 'MASTER COPY',
                'line_2' => 'LNU',
                'color' => $color,
                'font_family' => $layout['font_family'],
                'font_style' => $layout['font_style'],
                'font_size' => $layout['font_size'],
                'text_offset_x' => 0.0,
                'block_offset_y' => -2.0,
                'line_gap' => 5.0,
                'line_2_offset_x' => 0.0,
            ]
        );
    }

    private function drawControlledStampVariant(
        TcpdfFpdi $pdf,
        float $pageWidth,
        float $pageHeight,
        array $color
    ): void {
        $layout = $this->controlledCopyLayout();

        [$stampX, $stampY] = $this->controlledStampCoordinates($pageWidth, $pageHeight);

        $this->drawTwoLineStamp(
            $pdf,
            $stampX,
            $stampY,
            $layout['stamp_w'],
            $layout['stamp_h'],
            [
                'line_1' => 'CONTROLLED COPY',
                'line_2' => 'LNU',
                'color' => $color,
                'font_family' => $layout['font_family'],
                'font_style' => $layout['font_style'],
                'font_size' => $layout['font_size'],
                'line_1_font_size' => $layout['line_1_font_size'],
                'line_2_font_size' => $layout['line_2_font_size'],
                'text_offset_x' => $layout['text_offset_x'],
                'block_offset_y' => $layout['block_offset_y'],
                'line_gap' => $layout['line_gap'],
                'line_2_offset_x' => $layout['line_2_offset_x'],
            ]
        );
    }

    private function masterStampCoordinates(float $pageWidth, float $pageHeight): array
    {
        $layout = $this->masterCopyLayout();

        $boxX = $pageWidth - $layout['box_right_offset'] - $layout['box_w'];
        $boxY = $pageHeight - $layout['box_bottom_offset'] - $layout['box_h'];

        $stampX = $boxX + (($layout['box_w'] - $layout['stamp_w']) / 2) + $layout['stamp_offset_x'];
        $stampY = $boxY + (($layout['box_h'] - $layout['stamp_h']) / 2) + $layout['stamp_offset_y'];

        return [$stampX, $stampY];
    }

    private function controlledStampCoordinates(float $pageWidth, float $pageHeight): array
    {
        $masterLayout = $this->masterCopyLayout();
        $controlledLayout = $this->controlledCopyLayout();

        [$masterStampX, $masterStampY] = $this->masterStampCoordinates($pageWidth, $pageHeight);

        $stampX = $masterStampX + $masterLayout['stamp_w'] + $controlledLayout['gap_from_master'] + $controlledLayout['stamp_offset_x'];
        $stampY = $masterStampY + (($masterLayout['stamp_h'] - $controlledLayout['stamp_h']) / 2) + $controlledLayout['stamp_offset_y'];

        return [$stampX, $stampY];
    }

    private function uncontrolledStampCoordinates(float $pageWidth, float $pageHeight): array
    {
        $masterLayout = $this->masterCopyLayout();
        $controlledLayout = $this->controlledCopyLayout();
        $uncontrolledLayout = $this->uncontrolledCopyLayout();

        [$masterStampX, $masterStampY] = $this->masterStampCoordinates($pageWidth, $pageHeight);
        [$controlledStampX, $controlledStampY] = $this->controlledStampCoordinates($pageWidth, $pageHeight);

        $pairLeft = min($masterStampX, $controlledStampX);
        $pairRight = max(
            $masterStampX + $masterLayout['stamp_w'],
            $controlledStampX + $controlledLayout['stamp_w']
        );
        $pairWidth = $pairRight - $pairLeft;

        $stampX = $pairLeft + (($pairWidth - $uncontrolledLayout['stamp_w']) / 2) + $uncontrolledLayout['stamp_offset_x'];
        $stampY = min($masterStampY, $controlledStampY)
            - $uncontrolledLayout['stamp_h']
            - $uncontrolledLayout['gap_above_pair']
            + $uncontrolledLayout['stamp_offset_y'];

        return [$stampX, $stampY];
    }

    private function drawTwoLineStamp(
        TcpdfFpdi $pdf,
        float $stampX,
        float $stampY,
        float $stampWidth,
        float $stampHeight,
        array $config
    ): void {
        [$red, $green, $blue] = $config['color'];

        $pdf->SetDrawColor($red, $green, $blue);
        $pdf->SetTextColor($red, $green, $blue);
        $pdf->SetLineWidth(0.35);
        $pdf->Rect($stampX, $stampY, $stampWidth, $stampHeight);

        $pdf->SetFont(
            $config['font_family'],
            $config['font_style'],
            $config['font_size']
        );

        $line1 = $config['line_1'];
        $line2 = $config['line_2'];
        $line1FontSize = $config['line_1_font_size'] ?? $config['font_size'];
        $line2FontSize = $config['line_2_font_size'] ?? $config['font_size'];

        $pdf->SetFont(
            $config['font_family'],
            $config['font_style'],
            $line1FontSize
        );
        $line1Width = $pdf->GetStringWidth($line1);

        $pdf->SetFont(
            $config['font_family'],
            $config['font_style'],
            $line2FontSize
        );
        $line2Width = $pdf->GetStringWidth($line2);

        $textCenterX = $stampX + ($stampWidth / 2);
        $textOffsetX = $config['text_offset_x'];
        $blockOffsetY = $config['block_offset_y'];
        $lineGap = $config['line_gap'];
        $line2OffsetX = $config['line_2_offset_x'];

        $startY = $stampY + ($stampHeight / 2) - ($lineGap / 2) + $blockOffsetY;

        $pdf->SetFont(
            $config['font_family'],
            $config['font_style'],
            $line1FontSize
        );
        $pdf->Text(
            $textCenterX - ($line1Width / 2) + $textOffsetX,
            $startY,
            $line1
        );

        $pdf->SetFont(
            $config['font_family'],
            $config['font_style'],
            $line2FontSize
        );
        $pdf->Text(
            $textCenterX - ($line2Width / 2) + $textOffsetX + $line2OffsetX,
            $startY + $lineGap,
            $line2
        );
    }

    private function masterCopyLayout(): array
    {
        return [
            'box_w' => 22.0,
            'box_h' => 42.0,
            'box_right_offset' => 75.0,
            'box_bottom_offset' => 11.5,
            'stamp_w' => 34.0,
            'stamp_h' => 16.0,
            'stamp_offset_x' => -1.5,
            'stamp_offset_y' => 1.5,
            'font_family' => 'helvetica',
            'font_style' => '',
            'font_size' => 12,
        ];
    }

    private function controlledCopyLayout(): array
    {
        return [
            'gap_from_master' => 4.2,
            'stamp_w' => 34.0,
            'stamp_h' => 16.0,
            'stamp_offset_x' => 0.0,
            'stamp_offset_y' => 0.0,
            'font_family' => 'helvetica',
            'font_style' => '',
            'font_size' => 10.5,
            'line_1_font_size' => 9.0,
            'line_2_font_size' => 10.5,
            'text_offset_x' => 0.0,
            'block_offset_y' => -1.4,
            'line_gap' => 4.6,
            'line_2_offset_x' => 0.0,
        ];
    }

    private function uncontrolledCopyLayout(): array
    {
        return [
            'gap_above_pair' => 2.0,
            'stamp_w' => 39.0,
            'stamp_h' => 16.0,
            'stamp_offset_x' => 0.0,
            'stamp_offset_y' => 0.0,
            'font_family' => 'helvetica',
            'font_style' => '',
            'font_size' => 10.5,
            'line_1_font_size' => 9.0,
            'line_2_font_size' => 10.5,
            'text_offset_x' => 0.0,
            'block_offset_y' => -1.4,
            'line_gap' => 4.6,
            'line_2_offset_x' => 0.0,
        ];
    }
}