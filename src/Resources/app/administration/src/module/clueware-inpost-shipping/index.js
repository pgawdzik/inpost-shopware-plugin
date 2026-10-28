import './page/clueware-inpost-shipping-redirect';

const { Module } = Shopware;

Module.register('clueware-inpost-shipping', {
    type: 'plugin',
    name: 'clueware-inpost-shipping',
    title: 'clueware-inpost.general.mainMenuItemGeneral',
    description: 'clueware-inpost.general.description',
    version: '2.0.6',
    targetVersion: '2.0.6',
    color: '#52667a',
    icon: 'regular-truck',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'clueware-inpost-shipping-redirect',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.plugin_maintain',
            },
        },
    },

    settingsItem: {
        group: 'plugins',
        to: 'clueware.inpost.shipping.index',
        icon: 'regular-truck',
        privilege: 'system.plugin_maintain',
    },
});
