const scripts = [
  { name: 'makeTextPDF',
    src: 'xcc-pdf-editor/js/makeTextPDF.min.js'
  },
];

const assets = {};
export function getAsset(name) {
  if (assets[name]) return assets[name];
  const script = scripts.find((s) => s.name === name);
  if (!script) throw new Error(`Script ${name} not exists.`);
  return prepareAsset(script);
}

export function prepareAsset({ name, src }) {
  if (assets[name]) return assets[name];
  assets[name] = new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = src;
    script.onload = () => {
      resolve(window[name]);
      console.log(`${name} is loaded.`);
    };
    script.onerror = () => {
      reject(`The script ${name} didn't load correctly.`);
      alert(`Some scripts did not load correctly. Please reload and try again.`)
    };
    document.body.appendChild(script);
  });
  return assets[name];
}

export default function prepareAssets() {
  scripts.forEach(prepareAsset);
}

// out of the box fonts
const fonts = {
  // Courier: {
  //   correction(size, lineHeight) {
  //     return (size * lineHeight - size) / 2 + size / 6;
  //   },
  // },
  // Helvetica: {
  //   correction(size, lineHeight) {
  //     return (size * lineHeight - size) / 2 + size / 10;
  //   },
  // },
  'Times-Roman': {
    correction(size, lineHeight) {
      return (size * lineHeight - size) / 2 + size / 7;
    },
  },
};
// Available fonts
export const Fonts = {
  ...fonts,
  '宋体': {
    correction(size, lineHeight) {
      return (size * lineHeight - size) / 2 + size / 7;
    },
    src: 'xcc-pdf-editor/fonts/SongTi.ttf',
  },
  '微软雅黑': {
    correction(size, lineHeight) {
      return (size * lineHeight - size) / 2 + size / 7;
    },
    src: 'xcc-pdf-editor/fonts/WeiRuanYaHei.ttf',
  },
  '方正小标宋简': {
    correction(size, lineHeight) {
      return (size * lineHeight - size) / 2 + size / 7;
    },
    src: 'xcc-pdf-editor/fonts/FangZhengXiaoBiaoSongJian.ttf',
  },
  '楷体': {
    correction(size, lineHeight) {
      return (size * lineHeight - size) / 2 + size / 7;
    },
    src: 'xcc-pdf-editor/fonts/KaiTi.ttf',
  },
  '等线': {
    correction(size, lineHeight) {
      return (size * lineHeight - size) / 2 + size / 7;
    },
    src: 'xcc-pdf-editor/fonts/DengXian.ttf',
  },
  '黑体': {
    correction(size, lineHeight) {
      return (size * lineHeight - size) / 2 + size / 7;
    },
    src: 'xcc-pdf-editor/fonts/HeiTi.ttf',
  },
};

export function fetchFont(name) {
  if (fonts[name]) return fonts[name];
  const font = Fonts[name];
  if (!font) throw new Error(`Font '${name}' not exists.`);
  fonts[name] = fetch(font.src)
    .then((r) => r.arrayBuffer())
    .then((fontBuffer) => {
      const fontFace = new FontFace(name, fontBuffer);
      fontFace.display = 'swap';
      fontFace.load().then(() => document.fonts.add(fontFace));
      return {
        ...font,
        buffer: fontBuffer,
      };
    });
  return fonts[name];
}
