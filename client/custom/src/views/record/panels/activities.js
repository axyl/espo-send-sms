Espo.define('custom:views/record/panels/activities', ['crm:views/record/panels/activities'],
function (ActivitiesPanelView) {

    class CustomActivitiesPanelView extends ActivitiesPanelView {

        setup() {
            super.setup();

            this.setupSendSmsAction();
        }

        setupSendSmsAction() {
            if (!this.shouldAddSendSmsAction()) {
                return;
            }

            this.addSendSmsAction();
        }

        shouldAddSendSmsAction() {
            return this.name === 'activities' &&
                this.hasSmsProviderConfigured() &&
                this.isSmsSupportedEntity();
        }

        hasSmsProviderConfigured() {
            return !!this.getConfig().get('smsProvider');
        }

        isSmsSupportedEntity() {
            const entityType = this.model.entityType;
            const scopeType = this.getMetadata().get(['scopes', entityType, 'type']);

            return scopeType === 'Company' || scopeType === 'Person' || this.model.hasField('phoneNumber');
        }

        getSmsPhoneNumber() {
            const phoneNumber = this.model.get('phoneNumber');

            if (phoneNumber) {
                return phoneNumber;
            }

            return this.getFirstPhoneNumberFromData(this.model.get('phoneNumberData') || []);
        }

        getFirstPhoneNumberFromData(phoneNumberData) {
            for (const item of phoneNumberData) {
                if (item && item.phoneNumber) {
                    return item.phoneNumber;
                }
            }

            return '';
        }

        getSmsParentData() {
            return {
                parentType: this.model.entityType,
                parentId: this.model.id,
                parentName: this.model.get('name'),
            };
        }

        addSendSmsAction() {
            this.actionList.unshift(this.buildSendSmsActionItem());

            if (this.shouldAddSendSmsButton()) {
                this.buttonList.unshift(this.buildSendSmsButtonItem());
            }
        }

        buildSendSmsActionItem() {
            return {
                action: 'sendSms',
                label: 'Send SMS',
                acl: 'create',
                aclScope: 'Sms',
            };
        }

        shouldAddSendSmsButton() {
            if (!this.buttonMaxCount) {
                return false;
            }

            return this.buttonList.length < this.buttonMaxCount;
        }

        buildSendSmsButtonItem() {
            return {
                action: 'sendSms',
                title: 'Send SMS',
                acl: 'create',
                aclScope: 'Sms',
                html: this.getSendSmsButtonHtml(),
            };
        }

        getSendSmsButtonHtml() {
            const iconClass = this.getMetadata().get('clientDefs.Sms.iconClass') || 'far fa-comment-dots';

            return '<span class="' + iconClass + '"></span>';
        }

        openComposeSmsModal(attributes) {
            this.createView('composeSms', 'custom:views/modals/compose-sms', attributes, function (view) {
                view.render();

                this.listenForSmsSent(view);
            }.bind(this));
        }

        listenForSmsSent(view) {
            this.listenToOnce(view, 'after:send', this.handleSmsSent.bind(this));
        }

        handleSmsSent() {
            this.model.trigger('after:relate');

            // A single global refresh is sufficient here. Triggering both
            // update-related:smses and update-all causes overlapping
            // history fetches for the mixed-entity collection.
            this.model.trigger('update-all');
        }

        // noinspection JSUnusedGlobalSymbols
        actionSendSms() {
            this.openComposeSmsModal(this.getComposeSmsAttributes());
        }

        getComposeSmsAttributes() {
            return Object.assign({
                toNumber: this.getSmsPhoneNumber(),
            }, this.getSmsParentData());
        }
    }

    return CustomActivitiesPanelView;
});
