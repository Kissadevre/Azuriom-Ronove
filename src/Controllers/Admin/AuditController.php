<?php

namespace Azuriom\Plugin\Ronove\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\ActionLog;
use Azuriom\Plugin\Ronove\Services\TranslationAudit;
use Azuriom\Plugin\Ronove\Support\TranslationAuditIssue;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(TranslationAudit $audit)
    {
        return view('ronove::admin.audit.index', [
            'report' => $audit->inspect(),
            'categories' => TranslationAuditIssue::CATEGORIES,
        ]);
    }

    public function cleanup(Request $request, TranslationAudit $audit, string $category)
    {
        abort_unless(in_array($category, TranslationAuditIssue::CLEANABLE_CATEGORIES, true), 404);
        $request->validate(['confirm' => ['accepted']]);
        $count = $audit->cleanup($category);

        ActionLog::log('ronove.audit.cleaned', data: [
            'category' => $category,
            'count' => $count,
        ]);

        return to_route('ronove.admin.audit.index')
            ->with('success', trans_choice('ronove::admin.audit.cleaned', $count, ['count' => $count]));
    }
}
