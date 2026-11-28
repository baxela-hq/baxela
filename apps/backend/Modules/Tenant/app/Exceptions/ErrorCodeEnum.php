<?php

namespace Modules\Tenant\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case TENANT_CREATION_FAILED = 'tenant.tenant.creation_failed';

    case TENANT_UPDATE_FAILED = 'tenant.tenant.update_failed';

    case TENANT_SLUG_TAKEN = 'tenant.tenant.slug_taken';

    case TENANT_STATUS_CHANGE_FAILED = 'tenant.tenant.status_change_failed';

    case TENANT_DELETION_FAILED = 'tenant.tenant.deletion_failed';

    case DOMAIN_ATTACHMENT_FAILED = 'tenant.domain.attachment_failed';

    case DOMAIN_RELEASE_FAILED = 'tenant.domain.release_failed';

    case DOMAIN_IN_USE = 'tenant.domain.in_use';

    case DOMAIN_ALREADY_ATTACHED = 'tenant.domain.already_attached';

    case DOMAIN_COOLING_OFF = 'tenant.domain.cooling_off';

    case ONBOARDING_LIMIT_REACHED = 'tenant.onboarding.limit_reached';

    case ONBOARDING_FAILED = 'tenant.onboarding.failed';

    case MEMBERSHIP_ALREADY_MEMBER = 'tenant.membership.already_member';

    case MEMBERSHIP_LAST_OWNER = 'tenant.membership.last_owner';

    case MEMBERSHIP_OWNERSHIP_REQUIRED = 'tenant.membership.ownership_required';

    case MEMBERSHIP_INVITE_FAILED = 'tenant.membership.invite_failed';

    case MEMBERSHIP_CHANGE_FAILED = 'tenant.membership.change_failed';

    case MEMBERSHIP_ROLE_SYNC_FAILED = 'tenant.membership.role_sync_failed';
}
