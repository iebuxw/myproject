module.exports = {
  publicPath: '/',
  outputDir: 'dist',
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
