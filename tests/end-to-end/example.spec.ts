// import { test, expect } from '@playwright/test';
// import {readFileSync} from "fs";
// import {resolve} from "node:path";
// import {runCLI} from "@wp-playground/cli";
// import {beforeAll} from "vitest";
// import { Server, IncomingMessage, ServerResponse } from 'http';
// import { PHP, PHPRequestHandler} from '@php-wasm/universal';
//
// test.describe('Settings page', () => {
//
//     let server: Server
//     let handler: PHPRequestHandler
//     let php: PHP
//     let url: URL
//
//     test.beforeAll(async ({ browser }) => {
//         const blueprint = JSON.parse(
//             readFileSync(
//                 resolve("./blueprint.json"),"utf-8"
//             )
//         )
//
//         const cli = await runCLI({
//             "command": "server",
//             "mount": [
//                 {
//                     "hostPath": ".",
//                     "vfsPath": "/wordpress/wp-content/plugins/easysearch/"
//                 }
//
//             ],
//             blueprint
//         })
//
//         server = cli.server
//         handler = cli.requestHandler
//         php = await handler.getPrimaryPhp()
//
//         url = new URL(
//             "/wp-admin/admin.php?page=es_settings_page",
//             handler.absoluteUrl
//         )
//     })
//
//
//     test('Settings page has title', async ({ page }) => {
//         await page.goto(url.toString())
//
//         await expect(page).toHaveTitle(/Easy Search Settings/)
//     });
//     //
//     test('Settings page has options', async ({ page }) => {
//         await page.goto(url.toString())
//
//         await expect(page.getByTestId("load_more_button_text")).toHaveValue(/Load More/)
//         await expect(page.getByTestId("no_entries_found_text")).toHaveValue(/No Entries Found/)
//     });
// })
//
//
//
// // import { test, expect } from '@playwright/test';
// //
// // test('has title', async ({ page }) => {
// //   await page.goto('https://playwright.dev/');
// //
// //   // Expect a title "to contain" a substring.
// //   await expect(page).toHaveTitle(/Playwright/);
// // });
//
