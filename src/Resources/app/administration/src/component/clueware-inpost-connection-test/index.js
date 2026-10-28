import template from './clueware-inpost-connection-test.html.twig';
import './clueware-inpost-connection-test.scss';

const { Component, Mixin } = Shopware;

Component.register('clueware-inpost-connection-test', {
    template,

    inject: ['cluewareInpostApiService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            testing: false,
        };
    },

    methods: {
        async testConnection() {
            this.testing = true;
            try {
                const result = await (typeof this.cluewareInpostApiService.testConnection === 'function'
                    ? this.cluewareInpostApiService.testConnection()
                    : this.cluewareInpostApiService.httpClient
                        .get('/_action/clueware/inpost/connection-test', {
                            headers: this.cluewareInpostApiService.getBasicHeaders(),
                        })
                        .then((response) => response.data));
                this.createNotificationSuccess({
                    title: this.$t('clueware-inpost.connectionTest.title'),
                    message: this.$tc('clueware-inpost.connectionTest.success', result.testedSalesChannels, {
                        count: result.testedSalesChannels,
                    }),
                });
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('clueware-inpost.connectionTest.title'),
                    message: error?.response?.data?.message || this.$t('clueware-inpost.connectionTest.error'),
                });
            } finally {
                this.testing = false;
            }
        },
    },
});
