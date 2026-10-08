import test from 'node:test';
import assert from 'node:assert/strict';
import { marksPercentage } from '../../resources/js/marks-percentage.js';

test('calculates obtained marks against the actual paper total', () => {
    assert.equal(marksPercentage(['40'], 50), 80);
    assert.equal(marksPercentage(['0'], 50), 0);
    assert.equal(marksPercentage(['50'], 50), 100);
    assert.equal(marksPercentage(['22.5'], 30), 75);
});
test('adds theory and practical and rounds to two decimal places', () => {
    assert.equal(marksPercentage(['60', '15'], 100), 75);
    assert.equal(marksPercentage(['1'], 3), 33.33);
});
test('leaves incomplete and absent results blank', () => {
    assert.equal(marksPercentage([''], 50), null);
    assert.equal(marksPercentage(['40', ''], 100), null);
    assert.equal(marksPercentage(['40'], 50, 'absent'), null);
});
test('does not show misleading percentages for invalid marks', () => {
    for (const values of [['51'], ['-1'], ['invalid']]) assert.equal(marksPercentage(values, 50), null);
    assert.equal(marksPercentage(['1'], 0), null);
});
