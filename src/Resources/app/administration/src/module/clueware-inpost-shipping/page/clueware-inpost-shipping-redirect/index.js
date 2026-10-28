Shopware.Component.register('clueware-inpost-shipping-redirect', {
    template: '<div></div>',

    created() {
        this.$router.replace({
            name: 'sw.extension.config',
            params: {
                namespace: 'CluewareInpostShipping',
            },
        });
    },
});
