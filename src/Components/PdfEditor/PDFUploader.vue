<template>
  <section class="pdf-uploader form">
    <a href="#" class="btn" @click.prevent.stop="openPicker">Upload</a>
    <span>or</span>
    <label class="url">
      <input
        v-model="url"
        placeholder="Enter a PDF url"
        @keyup.enter="validateUrl"
        @blur="validateUrl"
         />
    </label>
    <p v-if="error" class="error">
      {{error}}
    </p>
  </section>
</template>

<script>
let fsClient;
function getClient() {
  if (fsClient) {
    return Promise.resolve(fsClient);
  } else {
    return import(
      /* webpackChunkName: "filestack" */
      'filestack-js'
      ).then(({default: filestack}) => {
      return filestack.init(process.env.VUE_APP_FILESTACK_KEY);
    })
  }
}

export default {
  props: {
    documentError: {
      type: String,
      default: '',
    },
  },
  data() {
    return {
      url: undefined,
      urlError: undefined,
    };
  },
  watch: {},
  computed: {
    error() {
      return this.documentError || this.urlError;
    },
  },
  methods: {
    openPicker() {
      getClient().then(client => {
        client
          .pick({
            fromSources: ['local_file_system', 'imagesearch', 'facebook', 'instagram', 'dropbox'],
            accept: ['.pdf'],
            maxFiles: 1,
            maxSize: 10240000,
          })
          .then(response => this.onFilestack(response));
      });
    },

    onFilestack(response) {
      if (response.filesUploaded.length > 0) {
        const [file] = response.filesUploaded;
        this.url = file.url;
        this.$emit('updated', this.url);
      }
    },

    validateUrl() {
      const URL_REGEX = /https?:\/\/(www\.)?[-a-zA-Z0-9@:%._+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_+.~#?&//=]*)/;
      if (URL_REGEX.exec(this.url)) {
        this.urlError = undefined;
        this.$emit('updated', this.url);
      } else {
        this.urlError = 'Please enter a valid url';
      }
    },
  },
};
</script>

<style scoped>
.form {
  display: block;
}
span,
label {
  color: white;
  font-weight: bold;
  margin-left: 0.5em;
}
input {
  width: 15em;
}
.error {
  border: 1px solid red;
  background: pink;
  color: red;
  padding: 0.5em 3em;
  display: inline;
}
button {
  display: inline;
  padding: 0.5em;
  font-size: 1em;
}
a.btn {
  display: inline;
  padding: 0.5em 3em;
  background: rgb(54, 114, 160);
  border: 1px solid white;
  text-decoration: none;
  border-radius: 3px;
  color: white;
  font-weight: bold;
}
</style>
