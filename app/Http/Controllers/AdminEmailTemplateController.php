<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Support\AdminNavigation;
use App\Support\SiteResolver;
use App\Support\SystemEmailTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminEmailTemplateController extends Controller
{
    public function index(Request $request)
    {
        $hasTemplates = Schema::hasTable('email_templates');
        $templates = collect();
        $paginator = null;

        if ($hasTemplates) {
            $paginator = EmailTemplate::query()
                ->when($request->filled('template_name'), function ($query) use ($request) {
                    $term = '%'.trim((string) $request->string('template_name')).'%';
                    $query->where(function ($searchQuery) use ($term) {
                        $searchQuery
                            ->where('template_name', 'like', $term)
                            ->orWhere('subject', 'like', $term)
                            ->orWhere('body', 'like', $term);
                    });
                })
                ->orderBy(
                    $this->sortColumn((string) $request->input('column_name'), 'updated_at', ['template_name', 'subject', 'is_active', 'updated_at']),
                    $this->sortDirection((string) $request->input('sort'), 'desc')
                )
                ->paginate(20)
                ->withQueryString();

            $templates = $paginator;
        }

        return view('admin.tools.email-templates.index', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'hasTemplates' => $hasTemplates,
            'templates' => $templates,
            'paginator' => $paginator,
            'systemTemplates' => SystemEmailTemplates::catalog(),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless(Schema::hasTable('email_templates'), 404);

        return view('admin.tools.email-templates.form', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'template' => new EmailTemplate(['is_active' => 1]),
            'mode' => 'create',
            'systemTemplates' => SystemEmailTemplates::catalog(),
            'tokenLabels' => SystemEmailTemplates::tokenLabels(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Schema::hasTable('email_templates'), 404);

        $validated = $request->validate([
            'template_name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['required', 'in:0,1'],
        ], [], [
            'template_name' => 'template name',
            'subject' => 'subject',
            'body' => 'message body',
            'is_active' => 'status',
        ]);

        $now = now()->format('Y-m-d H:i:s');
        $adminUser = $request->attributes->get('adminUser');

        $payload = [
            'template_name' => $validated['template_name'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'is_active' => (int) $validated['is_active'],
            'created_by' => $adminUser?->user_name ?: 'admin',
            'updated_by' => $adminUser?->user_name ?: 'admin',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('email_templates', 'site_id')) {
            $payload['site_id'] = SiteResolver::forRequest($request)->id;
        }

        EmailTemplate::query()->create($payload);

        return redirect()->to(url('/v/email-templates.php'))
            ->with('success', 'Email template created successfully.');
    }

    public function edit(Request $request, int $template)
    {
        abort_unless(Schema::hasTable('email_templates'), 404);
        $template = EmailTemplate::query()->findOrFail($template);

        return view('admin.tools.email-templates.form', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'template' => $template,
            'mode' => 'edit',
            'systemTemplates' => SystemEmailTemplates::catalog(),
            'tokenLabels' => SystemEmailTemplates::tokenLabels(),
        ]);
    }

    public function update(Request $request, int $template)
    {
        abort_unless(Schema::hasTable('email_templates'), 404);
        $template = EmailTemplate::query()->findOrFail($template);

        $validated = $request->validate([
            'template_name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['required', 'in:0,1'],
        ], [], [
            'template_name' => 'template name',
            'subject' => 'subject',
            'body' => 'message body',
            'is_active' => 'status',
        ]);

        $template->update([
            'template_name' => $validated['template_name'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'is_active' => (int) $validated['is_active'],
            'updated_by' => $request->attributes->get('adminUser')?->user_name ?: 'admin',
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]);

        return redirect()->to(url('/v/email-templates.php'))
            ->with('success', 'Email template updated successfully.');
    }

    public function destroy(int $template)
    {
        abort_unless(Schema::hasTable('email_templates'), 404);
        $template = EmailTemplate::query()->findOrFail($template);

        $template->delete();

        return redirect()->to(url('/v/email-templates.php'))
            ->with('success', 'Email template deleted successfully.');
    }

    private function sortColumn(string $column, string $default, array $allowed): string
    {
        return in_array($column, $allowed, true) ? $column : $default;
    }

    private function sortDirection(string $direction, string $default = 'desc'): string
    {
        $direction = strtolower($direction);

        return in_array($direction, ['asc', 'desc'], true) ? $direction : $default;
    }
}
