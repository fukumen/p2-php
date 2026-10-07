// CP932 → UTF-8 デコード、istanbul instrument、カバレッジ集計
import { readFileSync } from 'node:fs';
import iconv from 'iconv-lite';
import { createInstrumenter } from 'istanbul-lib-instrument';
import libCoverage from 'istanbul-lib-coverage';

// CP932 の .js を読み込んで UTF-8 文字列として返す
export function loadCp932Source(path) {
  return iconv.decode(readFileSync(path), 'cp932');
}

// CP932 の .js を読み込んで instrument したコードを返す
export function loadInstrumentedCp932(path, filename) {
  const source = loadCp932Source(path);
  const instrumenter = createInstrumenter({
    esmodules: false,
    produceSourceMap: false,
    coverageVariable: '__coverage__',
  });
  return instrumenter.instrumentSync(source, filename);
}

// window.__coverage__ を istanbul-lib-coverage で集計する
export function getCoverageSummary() {
  const map = libCoverage.createCoverageMap(globalThis.__coverage__ || {});
  return map.getCoverageSummary();
}

export function getCoverageMap() {
  return libCoverage.createCoverageMap(globalThis.__coverage__ || {});
}
