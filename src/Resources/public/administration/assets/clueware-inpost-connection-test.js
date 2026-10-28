(() => {
    const { Component, Mixin } = Shopware;
    const registry = Component.getComponentRegistry();

    if (registry.has('clueware-inpost-connection-test')) {
        return;
    }

    Component.register('clueware-inpost-connection-test', {
        template: `
            <div class="clueware-inpost-connection-test">
                <div class="clueware-inpost-connection-test__content">
                    <strong class="clueware-inpost-connection-test__title">{{ $t('clueware-inpost.connectionTest.title') }}</strong>
                    <p class="clueware-inpost-connection-test__help">{{ $t('clueware-inpost.connectionTest.help') }}</p>
                </div>
                <sw-button class="clueware-inpost-connection-test__action" variant="secondary" :is-loading="testing" :disabled="testing" @click="testConnection">
                    {{ $t('clueware-inpost.connectionTest.action') }}
                </sw-button>
            </div>
        `,
        inject: ['cluewareInpostApiService'],
        mixins: [Mixin.getByName('notification')],
        data() {
            return { testing: false };
        },
        methods: {
            async testConnection() {
                this.testing = true;
                try {
                    const api = this.cluewareInpostApiService;
                    const result = await (typeof api.testConnection === 'function'
                        ? api.testConnection()
                        : api.httpClient
                            .get('/_action/clueware/inpost/connection-test', { headers: api.getBasicHeaders() })
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
})();
