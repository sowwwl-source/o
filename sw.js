export class Storage {
  constructor(state, env) {
    this.state = state;
  }

  async fetch(request) {
    let url = new URL(request.url);
    let key = url.pathname.slice(1);
    let value = await this.state.storage.get(key);
    return new Response(value);
  }
}

export default {
  async fetch(request, env) {
    let url = new URL(request.url);
    let id = env.STORAGE.idFromName(url.hostname);
    let stub = env.STORAGE.get(id);
    return await stub.fetch(request);
  }
};
