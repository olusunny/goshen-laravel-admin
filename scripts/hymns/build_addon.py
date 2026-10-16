"""Build the executable add-on only; approved lyrics are imported separately."""
import argparse
import hashlib
import json
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo


def build(source: Path, destination: Path) -> str:
    manifest = json.loads((source / 'addon.json').read_text(encoding='utf-8'))
    assert manifest['package_key'] == 'church-tools.digital-hymn-book'
    assert manifest['activate_on_install'] is False
    files = sorted(p for p in source.rglob('*') if p.is_file())
    assert files and len(files) < 100
    destination.parent.mkdir(parents=True, exist_ok=True)
    with ZipFile(destination, 'w', compression=ZIP_DEFLATED) as archive:
        for path in files:
            assert not path.is_symlink(), 'Package symlinks are not permitted'
            relative = path.relative_to(source).as_posix()
            assert relative.split('/')[0] in {'src', 'database', 'addon.json', 'composer.json', 'README.md'}
            info = ZipInfo(relative, date_time=(2026, 10, 16, 0, 0, 0))
            info.compress_type = ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            archive.writestr(info, path.read_bytes())
    with ZipFile(destination) as archive:
        assert archive.testzip() is None
        assert json.loads(archive.read('addon.json')) == manifest
    checksum = hashlib.sha256(destination.read_bytes()).hexdigest()
    destination.with_suffix(destination.suffix + '.sha256').write_text(checksum + '  ' + destination.name + '\n', encoding='ascii')
    return checksum


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('output', type=Path)
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[2]
    print(json.dumps({'zip': str(args.output), 'sha256': build(root / 'packages/digital-hymn-book-addon', args.output)}))
