Espo.define('custom:views/record/panels/history', ['crm:views/record/panels/history'],
function (HistoryPanelView) {

    const SMS_HISTORY_LAYOUT = {
        rows: [
            [
                {name: 'ico', view: 'crm:views/fields/ico'},
                {name: 'name', link: true, view: 'views/event/fields/name-for-history'},
            ],
            [
                {name: 'dateStart', soft: true},
                {name: 'assignedUser'},
            ],
        ],
    };

    class CustomHistoryPanelView extends HistoryPanelView {

        setup() {
            super.setup();

            this.ensureSmsLayout();
        }

        ensureSmsLayout() {
            if (this.listLayout.Sms) {
                return;
            }

            this.listLayout.Sms = Espo.Utils.cloneDeep(SMS_HISTORY_LAYOUT);
        }
    }

    return CustomHistoryPanelView;
});
