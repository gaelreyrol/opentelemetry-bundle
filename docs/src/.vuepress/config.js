import {defaultTheme} from '@vuepress/theme-default'
import {defineUserConfig} from 'vuepress'
import {viteBundler} from '@vuepress/bundler-vite'
import { prismjsPlugin } from '@vuepress/plugin-prismjs'
import {copyCodePlugin} from '@vuepress/plugin-copy-code'
import {gitPlugin} from '@vuepress/plugin-git'

const isProd = process.env.NODE_ENV === 'production'

export default defineUserConfig({
    lang: 'en-US',
    base: isProd ? '/opentelemetry-bundle/' : '/',

    title: 'OpenTelemetry Bundle',
    description: 'Traces, metrics, and logs instrumentation within your Symfony application',

    theme: defaultTheme({
        repo: 'https://github.com/FriendsOfOpenTelemetry/opentelemetry-bundle',
        docsRepo: 'https://github.com/FriendsOfOpenTelemetry/opentelemetry-bundle',
        docsDir: 'docs/src',
        lastUpdated: true,

        navbar: [
            {
                text: 'User Guide',
                children: [
                    '/user-guide/introduction.md',
                    '/user-guide/getting-started.md',
                    '/user-guide/configuration.md',
                    '/user-guide/troubleshooting.md',
                ],
            },
            {
                text: 'Instrumentation',
                children: [
                    '/instrumentation/introduction.md',
                    '/instrumentation/traces.md',
                    '/instrumentation/metrics.md',
                    '/instrumentation/logs.md',
                ],
            },
            {
                text: 'How To',
                children: [
                    '/how-to/docker-env-setup.md',
                    '/how-to/frankenphp-runtime.md',
                ],
            },
        ],

        sidebarDepth: 2,
        sidebar: [
            {
                text: 'User Guide',
                children: [
                    '/user-guide/introduction.md',
                    '/user-guide/getting-started.md',
                    '/user-guide/configuration.md',
                    '/user-guide/troubleshooting.md',
                ],
            },
            {
                text: 'Instrumentation',
                children: [
                    '/instrumentation/introduction.md',
                    '/instrumentation/traces.md',
                    '/instrumentation/metrics.md',
                    '/instrumentation/logs.md',
                ],
            },
            {
                text: 'How To',
                children: [
                    '/how-to/docker-env-setup.md',
                ],
            }
        ],
    }),

    plugins: [
        prismjsPlugin(),
        copyCodePlugin(),
        gitPlugin(),
    ],

    bundler: viteBundler({
        viteOptions: {
            css: {
                preprocessorOptions: {
                    scss: {
                        api: 'modern-compiler',
                    }
                }
            }
        }
    }),
})
