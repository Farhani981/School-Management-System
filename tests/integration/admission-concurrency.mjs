import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { writeFile, unlink } from 'node:fs/promises';
import { resolve } from 'node:path';

const execute = promisify(execFile);
const database = resolve(`storage/app/admission-concurrency-${process.pid}.sqlite`);
await writeFile(database, '');
try {
    await execute('php', ['tests/support/admission-worker.php', database, 'init']);
    const results = await Promise.all(Array.from({ length: 8 }, (_, index) => execute('php', ['tests/support/admission-worker.php', database, String(index + 1)])));
    const numbers = results.map(result => result.stdout.trim());
    assert.equal(new Set(numbers).size, 8);
    assert.deepEqual(numbers.sort(), Array.from({ length: 8 }, (_, index) => `ADM-2026-${String(index + 1).padStart(4, '0')}`));
    console.log('PASS: eight simultaneous admissions generated unique consecutive numbers.');
} finally { await unlink(database); }
