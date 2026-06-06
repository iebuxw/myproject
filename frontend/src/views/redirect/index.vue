<script>
export default {
  name: 'Redirect',
  beforeCreate() {
    const { path } = this.$route.params
    this.$router.replace('/' + path)
  },
  render() {
    return null
  }
}
</script>
