const TerserPlugin = require('terser-webpack-plugin')

module.exports = {
  publicPath: '/',
  outputDir: 'dist',
  productionSourceMap: false,
  configureWebpack: {
    optimization: {
      minimizer: [
        new TerserPlugin({
          terserOptions: {
            compress: {
              drop_console: true,
              drop_debugger: true
            }
          }
        })
      ]
    }
  },
  devServer: {
    port: 8081,
    proxy: {
      '/admin': {
        target: 'http://127.0.0.1',
        changeOrigin: true
      }
    }
  }
}
