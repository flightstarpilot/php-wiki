<?php
declare(strict_types=1);

namespace Wiki\Services;

use Wiki\Models\Issue;
use Wiki\Models\Proposal;
use Wiki\Models\Settings;
use Wiki\Models\User;

class NotificationService
{
    /** Notify everyone who participated in an issue, except the actor. */
    public static function notifyIssueComment(int $issueId, int $actorUserId, string $commentBody): void
    {
        if (!Settings::getBool('mail.enabled')) return;

        $issue = Issue::find($issueId);
        if (!$issue) return;

        $recipients = self::participantsOf('issue', $issueId, (int)($issue['created_by'] ?? 0));
        $recipients = array_diff($recipients, [$actorUserId]);

        $url = url('issue/' . $issueId);
        $subject = MailService::render(
            I18nService::t('issue.comment_notify_subject', ['id' => $issueId]),
            ['id' => $issueId]
        );

        foreach ($recipients as $uid) {
            $u = User::find((int)$uid);
            if (!$u || $u['status'] !== 'active') continue;
            if (!self::wantsCommentMail($u)) continue;

            $body = self::buildBody((string)$u['username'], (string)$issue['title'], $commentBody, $url);
            MailService::send((string)$u['email'], $subject, $body);
        }
    }

    /** Notify everyone who participated in a proposal, except the actor. */
    public static function notifyProposalComment(int $proposalId, int $actorUserId, string $commentBody): void
    {
        if (!Settings::getBool('mail.enabled')) return;

        $proposal = Proposal::find($proposalId);
        if (!$proposal) return;

        $recipients = self::participantsOf('proposal', $proposalId, (int)($proposal['created_by'] ?? 0));
        $recipients = array_diff($recipients, [$actorUserId]);

        $url = url('proposal/' . $proposalId);
        $subject = MailService::render(
            I18nService::t('proposal.comment_notify_subject', ['id' => $proposalId]),
            ['id' => $proposalId]
        );

        foreach ($recipients as $uid) {
            $u = User::find((int)$uid);
            if (!$u || $u['status'] !== 'active') continue;
            if (!self::wantsCommentMail($u)) continue;

            $body = self::buildBody((string)$u['username'], (string)$proposal['title'], $commentBody, $url);
            MailService::send((string)$u['email'], $subject, $body);
        }
    }

    private static function participantsOf(string $targetType, int $targetId, int $creatorId): array
    {
        $ids = [$creatorId];
        try {
            $rows = \Wiki\Models\Comment::listFor($targetType, $targetId);
            foreach ($rows as $r) {
                if (!empty($r['created_by'])) $ids[] = (int)$r['created_by'];
            }
        } catch (\Throwable $e) {
            // comments table may be empty; ignore
        }
        return array_values(array_unique(array_filter($ids)));
    }

    private static function wantsCommentMail(array $user): bool
    {
        // Default on; users can opt out from the profile page.
        $key = 'user.notify_comments.' . (int)$user['id'];
        return Settings::get($key, '1') !== '0';
    }

    private static function buildBody(string $username, string $title, string $comment, string $url): string
    {
        return MailService::render(
            I18nService::t('notification.greeting', ['username' => $username])
            . "\n\n"
            . $title
            . "\n\n"
            . trim($comment)
            . "\n\n"
            . I18nService::t('notification.view_online', ['link' => $url])
            . "\n\n"
            . I18nService::t('notification.footer')
        );
    }
}
