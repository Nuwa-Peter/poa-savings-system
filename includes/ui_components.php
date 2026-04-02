<?php

/**
 * Renders a professional empty state component.
 *
 * @param string $icon Lucide icon name
 * @param string $title Title message
 * @param string $description Description text
 * @param string|null $cta_text Optional CTA button text
 * @param string|null $cta_link Optional CTA button link
 * @return string HTML string
 */
function renderEmptyState($icon, $title, $description, $cta_text = null, $cta_link = null) {
    $html = '<div class="flex flex-col items-center justify-center p-12 text-center bg-white rounded-xl border border-dashed border-slate-300">';
    $html .= '<div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">';
    $html .= '<i data-lucide="' . htmlspecialchars($icon) . '" class="w-8 h-8 text-slate-400"></i>';
    $html .= '</div>';
    $html .= '<h3 class="text-lg font-semibold text-slate-900 mb-1">' . htmlspecialchars($title) . '</h3>';
    $html .= '<p class="text-slate-500 max-w-xs mb-6">' . htmlspecialchars($description) . '</p>';

    if ($cta_text && $cta_link) {
        $html .= '<a href="' . htmlspecialchars($cta_link) . '" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition-colors shadow-sm">';
        $html .= '<i data-lucide="plus" class="w-4 h-4 mr-2"></i>';
        $html .= htmlspecialchars($cta_text);
        $html .= '</a>';
    }

    $html .= '</div>';
    return $html;
}
