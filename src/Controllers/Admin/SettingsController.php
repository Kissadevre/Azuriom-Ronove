<?php

namespace Azuriom\Plugin\Ronove\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\ActionLog;
use Azuriom\Models\Setting;
use Azuriom\Plugin\Ronove\Services\PublicLanguagePage;
use Azuriom\Plugin\Ronove\Services\ReviewWorkflow;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(ReviewWorkflow $workflow, PublicLanguagePage $publicPage)
    {
        return view('ronove::admin.settings', [
            'reviewWorkflowEnabled' => $workflow->enabled(),
            'publicLanguagePageEnabled' => $publicPage->enabled(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'review_workflow_enabled' => ['required', 'boolean'],
            'public_language_page_enabled' => ['required', 'boolean'],
        ]);

        Setting::updateSettings(
            ReviewWorkflow::SETTING_KEY,
            $validated['review_workflow_enabled'] ? '1' : '0',
        );
        Setting::updateSettings(
            PublicLanguagePage::SETTING_KEY,
            $validated['public_language_page_enabled'] ? '1' : '0',
        );

        ActionLog::log('ronove.settings.updated');

        return to_route('ronove.admin.settings.index')
            ->with('success', trans('ronove::admin.settings.updated'));
    }
}
