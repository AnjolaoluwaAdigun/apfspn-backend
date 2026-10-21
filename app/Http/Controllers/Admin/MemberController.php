<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Members\ApproveMemberRequest;
use App\Http\Requests\Admin\Members\DeleteMemberRequest;
use App\Http\Requests\Admin\Members\ExportMembersRequest;
use App\Http\Requests\Admin\Members\ListMembersRequest;
use App\Http\Requests\Admin\Members\MemberOverviewRequest;
use App\Http\Requests\Admin\Members\PendingMembersRequest;
use App\Http\Requests\Admin\Members\ReactivateMemberRequest;
use App\Http\Requests\Admin\Members\RejectMemberRequest;
use App\Http\Requests\Admin\Members\ShowMemberRequest;
use App\Http\Requests\Admin\Members\SuspendMemberRequest;
use App\Http\Requests\Admin\Members\UpdateMemberRequest;
use App\Http\Resources\MemberOverviewResource;
use App\Http\Resources\MemberResource;
use App\Services\Membership\AdminMemberService;
use App\Services\Membership\MemberApprovalService;
use App\Support\JsonResponser;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    public function __construct(
        private readonly AdminMemberService $members,
        private readonly MemberApprovalService $approvals,
    ) {}

    public function overview(MemberOverviewRequest $request): JsonResponse
    {
        return JsonResponser::success(new MemberOverviewResource($this->members->overview()));
    }

    public function index(ListMembersRequest $request): JsonResponse
    {
        return JsonResponser::success(MemberResource::collection($this->members->list($request->validated())));
    }

    public function pending(PendingMembersRequest $request): JsonResponse
    {
        return JsonResponser::success(MemberResource::collection($this->members->pending($request->validated())));
    }

    public function export(ExportMembersRequest $request): StreamedResponse
    {
        $rows = $this->members->export($request->validated());

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fputcsv($output, $row, escape: '');
            }

            fclose($output);
        }, 'apfspn-members-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(ShowMemberRequest $request, string $member): JsonResponse
    {
        $member = $this->members->show($member);

        return JsonResponser::success(new MemberResource($member));
    }

    public function update(UpdateMemberRequest $request, string $member): JsonResponse
    {
        return JsonResponser::success(new MemberResource($this->members->update($member, $request->validated())), 'Member updated.');
    }

    public function approve(ApproveMemberRequest $request, string $member): JsonResponse
    {
        $this->approvals->approve($member);

        return JsonResponser::success(new MemberResource($this->members->show($member)), 'Membership approved.');
    }

    public function reject(RejectMemberRequest $request, string $member): JsonResponse
    {
        $this->approvals->reject($member, $request->validated()['reason']);

        return JsonResponser::success(new MemberResource($this->members->show($member)), 'Membership rejected.');
    }

    public function suspend(SuspendMemberRequest $request, string $member): JsonResponse
    {
        $this->approvals->suspend($member, $request->validated()['reason']);

        return JsonResponser::success(new MemberResource($this->members->show($member)), 'Membership suspended.');
    }

    public function reactivate(ReactivateMemberRequest $request, string $member): JsonResponse
    {
        $this->approvals->reactivate($member);

        return JsonResponser::success(new MemberResource($this->members->show($member)), 'Membership reactivated.');
    }

    public function destroy(DeleteMemberRequest $request, string $member): JsonResponse
    {
        $this->members->delete($member);

        return JsonResponser::success(null, 'Member deleted.');
    }
}
