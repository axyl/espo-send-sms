Espo.define('custom:views/modals/compose-sms', ['views/modal'],
function (ModalView) {

    class ComposeSmsModalView extends ModalView {

        className = 'dialog dialog-record';
        cssName = 'compose-sms';
        scope = 'Sms';
        templateContent = `
            <div class="compose-sms-form">
                <div class="form-group">
                    <label class="control-label">{{translate 'To' scope='Sms'}}</label>
                    <input
                        type="text"
                        class="form-control"
                        name="to"
                        placeholder="{{translate 'phoneNumber' category='fields' scope='Contact'}}"
                        value="{{to}}"
                    >
                </div>
                <div class="form-group">
                    <label class="control-label">{{translate 'body' category='fields' scope='Sms'}}</label>
                    <textarea
                        class="form-control"
                        name="body"
                        rows="5"
                        placeholder="{{translate 'Message' scope='Sms'}}"
                        maxlength="1600"
                    >{{body}}</textarea>
                    <div class="text-muted small" style="margin-top: 4px;">
                        <span class="char-count">0</span> / 160
                    </div>
                </div>
            </div>
        `;

        setup() {
            super.setup();

            this.toNumber = this.options.toNumber || '';
            this.parentType = this.options.parentType || null;
            this.parentId = this.options.parentId || null;
            this.parentName = this.options.parentName || null;

            this.headerText = this.translate('Send SMS', 'labels', 'Sms');

            this.buttonList = [
                {
                    name: 'send',
                    label: 'Send',
                    style: 'primary',
                    onClick: function () { this.actionSend(); }.bind(this),
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                },
            ];
        }

        data() {
            return {
                to: this.toNumber,
                body: '',
            };
        }

        afterRender() {
            super.afterRender();

            const $textarea = this.$el.find('textarea[name="body"]');
            const $charCount = this.$el.find('.char-count');

            $textarea.on('input', function () {
                const len = $textarea.val().length;
                $charCount.text(len);

                if (len > 160) {
                    $charCount.addClass('text-warning');
                } else {
                    $charCount.removeClass('text-warning');
                }
            });
        }

        actionSend() {
            const to = this.$el.find('input[name="to"]').val().trim();
            const body = this.$el.find('textarea[name="body"]').val().trim();

            if (!to) {
                Espo.Ui.error(this.translate('toRequired', 'messages', 'Sms') || 'Phone number is required.');
                return;
            }

            if (!body) {
                Espo.Ui.error(this.translate('bodyRequired', 'messages', 'Sms') || 'Message body is required.');
                return;
            }

            this.disableButton('send');

            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

            const payload = {
                to: to,
                body: body,
                parentType: this.parentType,
                parentId: this.parentId,
                parentName: this.parentName,
            };

            const self = this;

            Espo.Ajax.postRequest('SmsCompose/action/send', payload)
                .then(function () {
                    Espo.Ui.notify(false);
                    Espo.Ui.success(self.translate('smsSent', 'messages', 'Sms') || 'SMS sent.');

                    self.trigger('after:send');
                    self.close();
                })
                .catch(function (xhr) {
                    self.enableButton('send');
                    Espo.Ui.notify(false);

                    const msg = (xhr.responseJSON && xhr.responseJSON.message) ||
                        self.translate('smsSendFailed', 'messages', 'Sms') ||
                        'Failed to send SMS.';

                    Espo.Ui.error(msg);
                });
        }
    }

    return ComposeSmsModalView;
});
