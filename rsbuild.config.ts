import { defineConfig } from '@rsbuild/core';

const stripTiptapInjectedCssPlugin = {
  name: 'strip-tiptap-injected-css',
  setup(api) {
    api.processAssets(
      { stage: 'optimize-size' },
      ({ assets, compilation, compiler }) => {
        for (const assetName of Object.keys(assets)) {
          if (!assetName.endsWith('.js')) continue;

          const asset = compilation.getAsset(assetName);
          if (!asset) continue;

          const source = asset.source.source().toString();
          const stripped = source.replace(
            /(var|let|const) ([A-Za-z_$][\w$]*)=`[^`]*ProseMirror-cursor-blink[^`]*`;/g,
            '$1 $2="";',
          );

          if (stripped === source) continue;

          compilation.updateAsset(
            assetName,
            new compiler.webpack.sources.RawSource(stripped),
          );
        }
      },
    );
  },
};

export default defineConfig({
  plugins: [stripTiptapInjectedCssPlugin],
  source: {
    entry: {
      'g3.admin.editor': './assets/js/es/g3.admin.editor.source.js',
    },
  },
  output: {
    target: 'web',
    distPath: {
      root: 'assets/js/es',
      js: '',
    },
    filename: {
      js: '[name].min.js',
    },
    filenameHash: false,
    minify: true,
    emitCss: false,
    legalComments: 'none',
    cleanDistPath: false,
    externals: {
      'vanilla-jui': 'vanilla-jui',
      'vanilla-signal': 'vanilla-signal',
      'vanilla-signal-i18n': 'vanilla-signal-i18n',
    },
  },
  server: {
    publicDir: false,
  },
  tools: {
    htmlPlugin: false,
    rspack: {
      externalsType: 'module',
      experiments: {
        outputModule: true,
      },
      output: {
        module: true,
        chunkFormat: 'module',
      },
      optimization: {
        runtimeChunk: false,
        splitChunks: false,
      },
    },
  },
});
