<?php

namespace Modules\Ecommerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Configuration\Services\EmailService;
use Modules\Ecommerce\Http\Controllers\Admin\Concerns\BuildsEcommerceDataTable;
use Modules\Ecommerce\Models\EcommerceContact;
use Modules\Ecommerce\Services\EcommerceContentService;

class ContactController extends Controller
{
    use BuildsEcommerceDataTable;

    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return $this->dataTable();
        }

        return view('ecommerce::admin.contacts.index');
    }

    protected function dataTable(): JsonResponse
    {
        $params = $this->dataTableParams();
        $base = EcommerceContact::query()->forCompany($this->companyId())->live();

        $recordsTotal = (clone $base)->count();
        $query = (clone $base)->orderByDesc('id');

        if ($params['search'] !== '') {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();
        $rows = $query->skip($params['start'])->take($params['length'])->get();
        $startingNumber = $recordsFiltered - $params['start'];

        $data = $rows->map(function (EcommerceContact $contact, int $index) use ($startingNumber) {
            $replyUrl = route('ecommerce.admin.contacts.reply', $contact->id);
            $destroyUrl = route('ecommerce.admin.contacts.destroy', $contact->id);
            $csrf = csrf_token();
            $replySubject = __('Re: :subject', ['subject' => $contact->subject]);

            $statusBadge = $contact->replied_at
                ? '<span class="badge bg-label-success">'.e(__('Replied')).'</span>'
                : '<span class="badge bg-label-warning">'.e(__('Pending')).'</span>';

            $emailHtml = '<a href="mailto:'.e($contact->email).'">'.e($contact->email).'</a>';
            if ($contact->phone) {
                $emailHtml .= '<div class="small text-muted">'.e($contact->phone).'</div>';
            }

            $actions = '<div class="d-inline-flex datatable-action">';
            $actions .= '<button type="button" class="btn btn-sm btn-icon" title="'.e(__('Reply')).'" '
                .'data-bs-toggle="modal" data-bs-target="#replyContactModal" '
                .'data-reply-url="'.e($replyUrl).'" '
                .'data-subject-default="'.e($replySubject).'" '
                .'data-contact-email="'.e($contact->email).'" '
                .'data-contact-name="'.e($contact->name).'">'
                .'<i class="ti tabler-mail-forward"></i></button>';
            $actions .= '<button type="button" data-id="'.$contact->id.'" class="delete_data btn btn-sm btn-icon delete-record" title="'.e(__('Delete')).'">'
                .'<i class="ti tabler-trash delete"></i></button>';
            $actions .= '<form id="deleteForm-'.$contact->id.'" action="'.e($destroyUrl).'" method="POST" style="display: none;">'
                .'<input type="hidden" name="_token" value="'.e($csrf).'">'
                .'<input type="hidden" name="_method" value="DELETE">'
                .'</form>';
            $actions .= '</div>';

            return [
                'id' => $startingNumber - $index,
                'actual_id' => $contact->id,
                'name' => $contact->name,
                'email' => $emailHtml,
                'subject' => Str::limit($contact->subject, 40),
                'message_preview' => '<span title="'.e($contact->message).'">'.e(Str::limit($contact->message, 60)).'</span>',
                'received' => optional($contact->created_at)->format('Y-m-d H:i'),
                'status_badge' => $statusBadge,
                'actions' => $actions,
            ];
        });

        return $this->dataTableResponse($params['draw'], $recordsTotal, $recordsFiltered, $data);
    }

    public function reply(Request $request, int $contact, EmailService $emailService): RedirectResponse
    {
        $model = $this->findLive($contact);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $branding = app(EcommerceContentService::class)->branding();
        $palette = \Modules\Ecommerce\Support\BrandColor::palette($branding['brandColor'] ?? null);
        $siteLogo = $branding['siteLogo'] ?? null;
        if (is_string($siteLogo) && $siteLogo !== '' && ! str_starts_with($siteLogo, 'http')) {
            $siteLogo = url($siteLogo);
        }

        $result = $emailService->sendEmail(
            $model->email,
            $validated['subject'],
            $validated['message'],
            [],
            'Ecommerce Contact Reply',
            null,
            [
                'company_id' => $this->companyId(),
                'siteName' => $branding['siteName'] ?? null,
                'siteLogo' => $siteLogo,
                'brandColor' => $palette['hex'],
                'brandAccent' => $palette['accent'],
                'brandStrong' => $palette['strong'],
                'brandSoft' => $palette['soft'],
                'brandGlow' => $palette['glow'],
                'customerName' => $model->name,
                'replyMessage' => $validated['message'],
                'originalSubject' => $model->subject,
            ]
        );

        if (($result['status'] ?? '') !== 'Success') {
            return back()->with('error', $result['message'] ?? __('Email could not be sent. Please check your mail configuration.'));
        }

        $model->update([
            'admin_reply' => $validated['message'],
            'replied_at' => now(),
        ]);

        return redirect()
            ->route('ecommerce.admin.contacts.index')
            ->with('success', __('Reply sent to :email.', ['email' => $model->email]));
    }

    public function destroy(int $contact): RedirectResponse
    {
        $model = $this->findLive($contact);
        $model->update(['del_status' => 'Deleted']);

        return redirect()
            ->route('ecommerce.admin.contacts.index')
            ->with('success', __('Contact message deleted successfully.'));
    }

    protected function findLive(int $id): EcommerceContact
    {
        return EcommerceContact::query()
            ->forCompany($this->companyId())
            ->live()
            ->where('id', $id)
            ->firstOrFail();
    }
}
