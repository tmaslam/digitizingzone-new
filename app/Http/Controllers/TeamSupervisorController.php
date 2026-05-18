<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Models\Attachment;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\SupervisorTeamMember;
use App\Support\PasswordManager;
use App\Support\TeamAccess;
use App\Support\TeamNavigation;
use App\Support\TeamWorkQueues;
use App\Support\TurnaroundTracking;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TeamSupervisorController extends Controller
{
    public function members(Request $request)
    {
        $supervisor = $request->attributes->get('teamUser');
        $members = TeamAccess::teamMembers($supervisor)
            ->when($request->filled('txtUserID'), fn ($collection) => $collection->filter(fn ($user) => str_contains((string) $user->user_id, trim((string) $request->string('txtUserID')))))
            ->when($request->filled('txtUserName'), function ($collection) use ($request) {
                $needle = strtolower(trim((string) $request->string('txtUserName')));

                return $collection->filter(fn ($user) => str_contains(strtolower((string) $user->user_name), $needle));
            })
            ->sortBy('user_name')
            ->values();

        $memberStats = $this->memberStats($supervisor, $members);

        return view('team.supervisor.members', [
            'teamUser' => $supervisor,
            'navCounts' => TeamNavigation::counts($supervisor->user_id, (int) $supervisor->usre_type_id),
            'members' => $members,
            'memberStats' => $memberStats,
        ]);
    }

    public function memberDetail(Request $request)
    {
        $supervisor = $request->attributes->get('teamUser');
        $member = $this->managedMember($supervisor, (int) $request->query('user_id'));

        $activeOrders = Order::query()
            ->with('assignee:user_id,user_name')
            ->active()
            ->where('assign_to', $member->user_id)
            ->whereIn('status', ['Underprocess', 'disapprove', 'disapproved'])
            ->orderByDesc('order_id')
            ->get();
        $this->decorateTurnaroundRows($activeOrders);

        $readyOrders = Order::query()
            ->with('assignee:user_id,user_name')
            ->active()
            ->where('assign_to', $member->user_id)
            ->where('status', 'Ready')
            ->orderByDesc('order_id')
            ->get();
        $this->decorateTurnaroundRows($readyOrders);

        $reviewComments = OrderComment::query()
            ->where('comment_source', 'supervisorReview')
            ->whereIn('order_id', $readyOrders->pluck('order_id'))
            ->get()
            ->keyBy('order_id');

        $stats = $this->memberStats($supervisor, collect([$member]))[$member->user_id] ?? $this->emptyStats();

        return view('team.supervisor.member-detail', [
            'teamUser' => $supervisor,
            'navCounts' => TeamNavigation::counts($supervisor->user_id, (int) $supervisor->usre_type_id),
            'member' => $member,
            'stats' => $stats,
            'activeOrders' => $activeOrders,
            'readyOrders' => $readyOrders,
            'reviewComments' => $reviewComments,
            'detailUrl' => fn (Order $order) => $this->detailUrl($order),
        ]);
    }

    public function reviewQueue(Request $request)
    {
        $supervisor = $request->attributes->get('teamUser');
        $memberIds = TeamAccess::teamMembers($supervisor)->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        $orders = Order::query()
            ->with('assignee:user_id,user_name')
            ->active()
            ->where('status', 'Ready')
            ->whereIn('assign_to', $memberIds)
            ->when($request->filled('txtUserID'), fn ($query) => $query->where('assign_to', (int) $request->query('txtUserID')))
            ->when($request->filled('txtOrderID'), fn ($query) => $query->where('order_id', 'like', '%'.trim((string) $request->string('txtOrderID')).'%'))
            ->orderByDesc('vender_complete_date')
            ->orderByDesc('order_id')
            ->get();
        $this->decorateTurnaroundRows($orders);

        $reviewComments = OrderComment::query()
            ->where('comment_source', 'supervisorReview')
            ->whereIn('order_id', $orders->pluck('order_id'))
            ->get()
            ->keyBy('order_id');

        return view('team.supervisor.review-queue', [
            'teamUser' => $supervisor,
            'navCounts' => TeamNavigation::counts($supervisor->user_id, (int) $supervisor->usre_type_id),
            'orders' => $orders,
            'members' => TeamAccess::teamMembers($supervisor)->keyBy('user_id'),
            'reviewComments' => $reviewComments,
            'detailUrl' => fn (Order $order) => $this->detailUrl($order),
        ]);
    }

    public function memberForm(Request $request)
    {
        $supervisor = $request->attributes->get('teamUser');
        $member = $request->filled('user_id')
            ? $this->managedMember($supervisor, (int) $request->query('user_id'))
            : new AdminUser(['usre_type_id' => AdminUser::TYPE_TEAM, 'is_active' => 1]);

        return view('team.supervisor.member-form', [
            'teamUser' => $supervisor,
            'navCounts' => TeamNavigation::counts($supervisor->user_id, (int) $supervisor->usre_type_id),
            'member' => $member,
            'mode' => $member->exists ? 'edit' : 'create',
        ]);
    }

    public function memberSave(Request $request)
    {
        $supervisor = $request->attributes->get('teamUser');
        $member = $request->filled('user_id')
            ? $this->managedMember($supervisor, (int) $request->input('user_id'))
            : new AdminUser();

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'txtTeamName' => ['required', 'string', 'max:150'],
            'txtPassword' => [$member->exists ? 'nullable' : 'required', 'string', 'min:6', 'max:100'],
            'txtCPassword' => [$member->exists ? 'nullable' : 'required', 'same:txtPassword'],
            'txtEmail' => ['required', 'email', 'max:150'],
        ], [
            'txtCPassword.same' => 'The confirm password must match the password.',
        ], [
            'txtTeamName' => 'user name',
            'txtPassword' => 'password',
            'txtCPassword' => 'confirm password',
            'txtEmail' => 'email address',
        ]);

        $duplicateQuery = AdminUser::query()
            ->teamPortalUsers()
            ->where('user_name', $validated['txtTeamName']);

        if ($member->exists) {
            $duplicateQuery->where('user_id', '!=', $member->user_id);
        }

        if ($duplicateQuery->exists()) {
            return back()->withErrors(['txtTeamName' => 'User name already exists.'])->withInput();
        }

        $member->fill([
            'user_name' => $validated['txtTeamName'],
            'user_email' => $validated['txtEmail'],
            'usre_type_id' => AdminUser::TYPE_TEAM,
            'is_active' => $member->exists ? (int) ($member->is_active ?? 1) : 1,
        ]);

        if (! $member->exists) {
            $member->fill([
                'security_key' => Str::random(40),
                'date_added' => now()->format('Y-m-d H:i:s'),
                'first_name' => '',
                'last_name' => '',
                'company' => '',
                'company_type' => '',
                'alternate_email' => '',
                'company_address' => '',
                'zip_code' => '',
                'user_city' => '',
                'user_country' => '',
                'user_phone' => '',
                'user_fax' => '',
                'contact_person' => '',
                'middle_fee' => 1.50,
                'super_fee' => 0,
                'userip_addrs' => '',
                'digitzing_format' => '',
                'vertor_format' => '',
                'topup' => '',
                'exist_customer' => '0',
                'user_term' => '',
                'package_type' => '',
                'real_user' => '0',
                'ref_code' => '',
                'ref_code_other' => '',
                'register_by' => $supervisor->user_name ?: 'supervisor',
            ]);
        }

        if ($request->filled('txtPassword')) {
            $member->fill(PasswordManager::payload((string) $validated['txtPassword']));
        }

        $member->save();

        if (Schema::hasTable('supervisor_team_members')) {
            SupervisorTeamMember::query()->updateOrCreate([
                'supervisor_user_id' => $supervisor->user_id,
                'member_user_id' => $member->user_id,
            ], [
                'date_added' => now()->format('Y-m-d H:i:s'),
                'end_date' => null,
                'deleted_by' => null,
            ]);
        }

        return redirect()->to(url('/team/manage-team.php'))
            ->with('success', $member->wasRecentlyCreated ? 'Team member created successfully.' : 'Team member updated successfully.');
    }

    public function assignForm(Request $request)
    {
        $supervisor = $request->attributes->get('teamUser');
        $order = $this->accessibleOrder($supervisor, (int) $request->query('design_id'));
        $page = in_array($request->query('page'), ['order', 'quote', 'qquote', 'vector'], true) ? (string) $request->query('page') : 'order';
        $order->loadMissing(['customer:user_id,user_name,first_name,last_name,user_email', 'assignee:user_id,user_name,user_email']);

        $shareableAttachments = Attachment::query()
            ->where('order_id', $order->order_id)
            ->whereIn('file_source', $page === 'quote' ? ['quote', 'vector', 'color', 'edit quote'] : ['order', 'vector', 'color', 'edit order'])
            ->orderByDesc('id')
            ->get();

        return view('team.supervisor.assign', [
            'teamUser' => $supervisor,
            'navCounts' => TeamNavigation::counts($supervisor->user_id, (int) $supervisor->usre_type_id),
            'order' => $order,
            'page' => $page,
            'assignableUsers' => TeamAccess::assignableUsers($supervisor),
            'backUrl' => $this->supervisorBackUrl($order, $page),
            'shareableAttachments' => $shareableAttachments,
            'turnaround' => TurnaroundTracking::summary($order),
        ]);
    }

    public function assignSave(Request $request)
    {
        $supervisor = $request->attributes->get('teamUser');

        $validated = $request->validate([
            'design_id' => ['required', 'integer'],
            'page' => ['required', 'in:order,quote,qquote,vector'],
            'team' => ['required', 'integer'],
            'handoff_comment' => ['nullable', 'string'],
        ], [], [
            'design_id' => 'order',
            'page' => 'page',
            'team' => 'team member',
            'handoff_comment' => 'handoff comment',
        ]);

        $order = $this->accessibleOrder($supervisor, (int) $validated['design_id']);
        abort_unless(in_array((int) $validated['team'], TeamAccess::assignableUsers($supervisor)->pluck('user_id')->map(fn ($id) => (int) $id)->all(), true), 404);

        if (filled($validated['handoff_comment'] ?? null)) {
            OrderComment::query()->create([
                'order_id' => $order->order_id,
                'comments' => $validated['handoff_comment'],
                'source_page' => 'orderTeamComments',
                'comment_source' => 'orderTeamComments',
                'date_added' => now()->format('Y-m-d H:i:s'),
                'date_modified' => now()->format('Y-m-d H:i:s'),
            ]);
        }

        $order->update([
            'assign_to' => (int) $validated['team'],
            'assigned_date' => now()->format('Y-m-d H:i:s'),
            'status' => in_array((string) $order->status, ['disapprove', 'disapproved'], true) ? 'disapprove' : 'Underprocess',
        ]);

        return redirect()->to($this->supervisorBackUrl($order, (string) $validated['page']))
            ->with('success', 'Work assignment updated successfully.');
    }

    public function markReviewed(Request $request)
    {
        $supervisor = $request->attributes->get('teamUser');

        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'review_note' => ['nullable', 'string'],
        ], [], [
            'order_id' => 'order',
            'review_note' => 'review note',
        ]);

        $order = $this->accessibleOrder($supervisor, (int) $validated['order_id']);
        abort_unless((string) $order->status === 'Ready', 404);
        abort_unless(in_array((int) $order->assign_to, TeamAccess::teamMembers($supervisor)->pluck('user_id')->map(fn ($id) => (int) $id)->all(), true), 404);

        $note = trim((string) ($validated['review_note'] ?? ''));
        $message = $note !== ''
            ? $note
            : 'Verified by supervisor '.$supervisor->user_name.' on '.now()->format('Y-m-d H:i:s');

        OrderComment::query()->updateOrCreate(
            [
                'order_id' => $order->order_id,
                'comment_source' => 'supervisorReview',
            ],
            [
                'comments' => $message,
                'source_page' => 'supervisorReview',
                'date_added' => now()->format('Y-m-d H:i:s'),
                'date_modified' => now()->format('Y-m-d H:i:s'),
            ]
        );

        return back()->with('success', 'Supervisor review saved successfully.');
    }

    private function managedMember(AdminUser $supervisor, int $memberId): AdminUser
    {
        abort_unless(TeamAccess::canManageUser($supervisor, $memberId), 404);

        return AdminUser::query()
            ->teams()
            ->active()
            ->where('is_active', 1)
            ->findOrFail($memberId);
    }

    private function accessibleOrder(AdminUser $supervisor, int $orderId): Order
    {
        return Order::query()
            ->active()
            ->where('order_id', $orderId)
            ->whereIn('assign_to', TeamAccess::accessibleUserIds($supervisor))
            ->firstOrFail();
    }

    private function supervisorBackUrl(Order $order, string $page): string
    {
        if ($page === 'qquote') {
            return url('/team/quick-quotes/'.$order->order_id.'/detail');
        }

        $act = in_array((string) $order->status, ['disapprove', 'disapproved'], true)
            ? 'disapproved'
            : ($page === 'quote' ? 'quote' : 'order');

        return TeamWorkQueues::detailUrl($order, $act);
    }

    private function memberStats(AdminUser $supervisor, Collection $members): array
    {
        if ($members->isEmpty()) {
            return [];
        }

        $memberIds = $members->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $orders = Order::query()
            ->active()
            ->whereIn('assign_to', $memberIds)
            ->get(['order_id', 'assign_to', 'status', 'working']);

        $reviewedOrderIds = OrderComment::query()
            ->where('comment_source', 'supervisorReview')
            ->whereIn('order_id', $orders->pluck('order_id'))
            ->pluck('order_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $reviewedLookup = array_fill_keys($reviewedOrderIds, true);

        $stats = [];
        foreach ($members as $member) {
            $memberOrders = $orders->where('assign_to', $member->user_id);
            $stats[$member->user_id] = [
                'active' => $memberOrders->whereIn('status', ['Underprocess', 'disapprove', 'disapproved'])->count(),
                'working' => $memberOrders->filter(fn ($order) => (string) $order->status === 'Underprocess' && trim((string) $order->working) !== '')->count(),
                'ready' => $memberOrders->where('status', 'Ready')->count(),
                'disapproved' => $memberOrders->whereIn('status', ['disapprove', 'disapproved'])->count(),
                'verified' => $memberOrders->filter(fn ($order) => (string) $order->status === 'Ready' && isset($reviewedLookup[(int) $order->order_id]))->count(),
            ];
        }

        return $stats;
    }

    private function emptyStats(): array
    {
        return [
            'active' => 0,
            'working' => 0,
            'ready' => 0,
            'disapproved' => 0,
            'verified' => 0,
        ];
    }

    private function decorateTurnaroundRows(Collection $orders): void
    {
        $orders->transform(function (Order $order) {
            $turnaround = TurnaroundTracking::summary($order);
            $order->turnaround_label = $turnaround['label_with_timing'];
            $order->turnaround_status_label = $turnaround['status_label'];
            $order->turnaround_status_tone = $turnaround['status_tone'];
            $order->turnaround_remaining_label = $turnaround['remaining_label'];

            return $order;
        });
    }

    private function detailUrl(Order $order): string
    {
        if ((string) $order->order_type === 'qquote') {
            return url('/team/quick-quotes/'.$order->order_id.'/detail');
        }

        $act = in_array((string) $order->status, ['disapprove', 'disapproved'], true)
            ? 'disapproved'
            : (in_array((string) $order->order_type, ['quote', 'digitzing', 'q-vector', 'qcolor'], true) ? 'quote' : 'order');

        return TeamWorkQueues::detailUrl($order, $act);
    }
}
