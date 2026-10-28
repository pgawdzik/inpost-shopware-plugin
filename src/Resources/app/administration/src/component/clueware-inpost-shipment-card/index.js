import template from './clueware-inpost-shipment-card.html.twig';
import './clueware-inpost-shipment-card.scss';

const { Mixin } = Shopware;

Shopware.Component.register('clueware-inpost-shipment-card', {
    template,

    inject: ['cluewareInpostApiService', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        orderId: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            state: null,
            loading: true,
            processing: false,
            form: {
                weight: 1,
                size: 'small',
                length: 400,
                width: 300,
                height: 200,
                codAmount: 0,
                labelFormat: 'A6',
            },
        };
    },

    computed: {
        isInpostOrder() {
            return ['locker', 'courier'].includes(this.state?.deliveryType);
        },

        canCreate() {
            return !this.state?.shipment || ['error', 'cancelled', 'returned'].includes(this.state.shipment.status);
        },

        pointAddress() {
            const point = this.state?.point;
            if (!point) {
                return '';
            }
            return [
                [point.street, point.buildingNumber].filter(Boolean).join(' '),
                [point.postCode, point.city].filter(Boolean).join(' '),
            ].filter(Boolean).join(', ');
        },

        statusLabel() {
            const status = this.state?.shipment?.status || 'draft';
            return this.$te(`clueware-inpost.status.${status}`)
                ? this.$t(`clueware-inpost.status.${status}`)
                : status;
        },

        trackingUrl() {
            const trackingNumber = this.state?.shipment?.trackingNumber;
            return trackingNumber
                ? `https://inpost.pl/sledzenie-przesylek?number=${encodeURIComponent(trackingNumber)}`
                : '';
        },

        canCancel() {
            return ['created', 'offers_prepared', 'offer_selected']
                .includes(this.state?.shipment?.externalStatus);
        },
    },

    watch: {
        orderId() {
            this.load();
        },
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            this.loading = true;
            try {
                this.state = await this.cluewareInpostApiService.getState(this.orderId);
                this.applyDefaults();
            } catch (error) {
                this.notifyError(error);
            } finally {
                this.loading = false;
            }
        },

        applyDefaults() {
            if (!this.state?.defaults) {
                return;
            }
            this.form = {
                weight: this.state.defaults.weight,
                size: this.state.defaults.size,
                length: this.state.defaults.length,
                width: this.state.defaults.width,
                height: this.state.defaults.height,
                codAmount: this.state.defaults.codAmount,
                labelFormat: this.state.defaults.labelFormat,
            };
        },

        async createShipment() {
            if (this.processing) {
                return;
            }
            this.processing = true;
            try {
                this.state = await this.cluewareInpostApiService.createShipment(this.orderId, this.form);
                this.createNotificationSuccess({ message: this.$t('clueware-inpost.messages.created') });
            } catch (error) {
                this.notifyError(error);
                await this.load();
            } finally {
                this.processing = false;
            }
        },

        async refreshShipment() {
            this.processing = true;
            try {
                this.state = await this.cluewareInpostApiService.refreshShipment(this.orderId);
                this.createNotificationSuccess({ message: this.$t('clueware-inpost.messages.refreshed') });
            } catch (error) {
                this.notifyError(error);
            } finally {
                this.processing = false;
            }
        },

        async cancelShipment() {
            if (!window.confirm(this.$t('clueware-inpost.messages.cancelConfirm'))) {
                return;
            }
            this.processing = true;
            try {
                this.state = await this.cluewareInpostApiService.cancelShipment(this.orderId);
                this.createNotificationSuccess({ message: this.$t('clueware-inpost.messages.cancelled') });
            } catch (error) {
                this.notifyError(error);
            } finally {
                this.processing = false;
            }
        },

        async downloadLabel() {
            this.processing = true;
            try {
                const response = await this.cluewareInpostApiService.downloadLabel(this.orderId);
                const blob = new Blob([response.data], { type: response.headers['content-type'] || 'application/pdf' });
                const disposition = response.headers['content-disposition'] || '';
                const match = disposition.match(/filename="?([^";]+)"?/i);
                const fileName = match?.[1] || 'inpost-label.pdf';
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
            } catch (error) {
                this.notifyError(error);
            } finally {
                this.processing = false;
            }
        },

        notifyError(error) {
            const message = error?.response?.data?.message || this.$t('clueware-inpost.messages.error');
            this.createNotificationError({ message });
        },
    },
});
