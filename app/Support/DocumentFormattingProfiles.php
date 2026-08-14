<?php

namespace App\Support;

class DocumentFormattingProfiles
{
    public static function options(): array
    {
        return [
            'legal_traditional' => 'Legal Traditional',
            'legal_modern' => 'Legal Modern',
            'court_filing' => 'Court Filing',
            'custom' => 'Custom',
        ];
    }

    public static function values(?string $profile): array
    {
        return match ($profile) {
            'legal_traditional' => [
                'font_family' => 'Times New Roman', 'font_size' => 12,
                'body_alignment' => 'both', 'line_spacing' => 1.15,
                'paragraph_space_after' => 6, 'margin_top' => 25.4,
                'margin_right' => 25.4, 'margin_bottom' => 25.4, 'margin_left' => 25.4,
                'heading_bold' => true, 'heading_size_step' => 2,
                'first_line_indent' => 0, 'left_indent' => 0, 'right_indent' => 0,
                'apply_paragraph_style_to_clauses' => true, 'page_numbers' => true,
            ],
            'legal_modern' => [
                'font_family' => 'Arial', 'font_size' => 11,
                'body_alignment' => 'left', 'line_spacing' => 1.15,
                'paragraph_space_after' => 6, 'margin_top' => 25.4,
                'margin_right' => 25.4, 'margin_bottom' => 25.4, 'margin_left' => 25.4,
                'heading_bold' => true, 'heading_size_step' => 2,
                'first_line_indent' => 0, 'left_indent' => 0, 'right_indent' => 0,
                'apply_paragraph_style_to_clauses' => true, 'page_numbers' => true,
            ],
            'court_filing' => [
                'font_family' => 'Times New Roman', 'font_size' => 12,
                'body_alignment' => 'left', 'line_spacing' => 2.0,
                'paragraph_space_after' => 0, 'margin_top' => 25.4,
                'margin_right' => 25.4, 'margin_bottom' => 25.4, 'margin_left' => 38.1,
                'heading_bold' => true, 'heading_size_step' => 1,
                'first_line_indent' => 0, 'left_indent' => 0, 'right_indent' => 0,
                'apply_paragraph_style_to_clauses' => true, 'page_numbers' => true,
            ],
            default => [],
        };
    }
}
