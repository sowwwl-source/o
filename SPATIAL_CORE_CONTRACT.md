# Spatial Core Contract

This note defines the bridge between the current web spatial shell and future native headset clients.

## Goal

The headset client must not reinvent O.

It should inject native spatial state into the same product logic already used by:

- the torus
- RA modulation
- Terre & Mine
- spatial context routing
- public/private route suggestions

## Current bridge surface

The web app now exposes:

- meta `o-spatial-native-contract`
- meta `o-spatial-native-event`
- global `window.OBridgeSpatialNative`
- local simulator `window.OBridgeSpatialNativeSimulator`
- seed globals `window.__O_NATIVE_SPATIAL__` or `window.__O_SPATIAL_NATIVE__`
- custom event `o:native-spatial-state`
- derived event `o:spatial-native-change`

The contract version is currently:

- `2026-05-26`

## Public API

### Inject directly

```js
window.OBridgeSpatialNative.updateState({
  source: "visionos",
  platform: {
    family: "apple",
    name: "vision pro",
    runtime: "visionos",
    version: "2.0",
    capabilities: ["gaze", "pinch", "anchors", "room", "light-estimation", "passthrough", "spatial-audio"],
  },
  session: {
    mode: "mixed",
    space: "full",
    immersion: "mixed",
    phase: "live",
    focus: "foreground",
    safety: "clear",
  },
  inputs: {
    primary: "gaze",
    gazeAvailable: true,
    pinchAvailable: true,
    handTrackingAvailable: true,
  },
  pose: {
    head: {
      tracked: true,
      position: [0, 1.42, 0],
      rotation: [0, 0, 0, 1],
      speed: 0.18,
    },
    gaze: {
      tracked: true,
      origin: [0, 1.42, 0],
      direction: [0.12, -0.08, -0.98],
      stability: 0.84,
    },
  },
  hands: {
    activeCount: 2,
    left: { tracked: true, pinch: 0.22, grab: 0.08, confidence: 0.92, jointsTracked: 25 },
    right: { tracked: true, pinch: 0.61, grab: 0.12, confidence: 0.94, jointsTracked: 25 },
  },
  world: {
    passthrough: "mixed",
    anchorsTracked: 4,
    planesTracked: 3,
    roomTracked: true,
    lightLevel: 0.68,
    lightContrast: 0.41,
    lightDirectionX: 0.34,
    lightDirectionY: -0.22,
    anchorStability: 0.78,
  },
  audio: {
    spatial: true,
    outputRoute: "headset",
    inputRoute: "beamforming-mic",
  },
});
```

### Emit by event

```js
window.dispatchEvent(new CustomEvent("o:native-spatial-state", {
  detail: {
    source: "quest",
    session: { mode: "mixed", space: "shared", immersion: "mixed" },
    inputs: { primary: "pinch", pinchAvailable: true, handTrackingAvailable: true },
    world: { passthrough: "mixed", anchorsTracked: 2, roomTracked: true },
  },
}));
```

### Read normalized state

```js
const spatial = window.OBridgeSpatialNative.readState();
```

### Drive the local simulator

Available only on local `io` previews.

```js
window.OBridgeSpatialNativeSimulator.enablePreset("visionos");
window.OBridgeSpatialNativeSimulator.readState();
window.OBridgeSpatialNativeSimulator.shareUrl();
window.OBridgeSpatialNativeSimulator.disable();
```

Useful local replay params:

- `native_run=1`
- `native_sim=visionos|quest|browser`
- `native_scenario=idle|lightSweep|roomWalk|duetWeave`

## Normalized shape

Top-level keys accepted now:

- `source`
- `available`
- `platform`
- `session`
- `inputs`
- `pose`
- `hands`
- `world`
- `audio`

### `platform`

- `family`
- `name`
- `runtime`
- `version`
- `build`
- `capabilities[]`

### `session`

- `mode`: `screen | vr | ar | mixed | headset`
- `space`: `screen | window | volume | shared | mixed | full | immersive`
- `immersion`: `windowed | mixed | progressive | full | immersive | portal`
- `phase`
- `focus`
- `safety`

### `inputs`

- `primary`
- `gazeAvailable`
- `pinchAvailable`
- `handTrackingAvailable`
- `controllerAvailable`
- `voiceAvailable`

### `pose`

- `head.tracked`
- `head.position`
- `head.rotation`
- `head.speed`
- `gaze.tracked`
- `gaze.origin`
- `gaze.direction`
- `gaze.stability`

### `hands`

- `activeCount`
- `left`
- `right`

Each hand can include:

- `tracked`
- `pinch`
- `grab`
- `confidence`
- `jointsTracked`
- `gesture`

### `world`

- `passthrough`
- `anchorsTracked`
- `planesTracked`
- `meshTracked`
- `roomTracked`
- `lightLevel`
- `lightContrast`
- `lightDirectionX`
- `lightDirectionY`
- `anchorStability`

### `audio`

- `spatial`
- `route`
- `outputRoute`
- `inputRoute`

## What the web shell does with it

The current bridge already feeds:

- torus light/direction fallback
- torus gaze tilt fallback
- native hand energy fallback for Terre & Mine
- spatial context readout: runtime / space / input / anchoring
- device bridge copy for headset-native previews

This means a native client can start useful integration before full volumetric rendering is ready.

## What this is not yet

This contract does not yet carry:

- persistent anchor ids
- full joint arrays
- scene meshes
- semantic room labels
- native route intents for every O. door
- volumetric object transforms for each route card

Those should be added only once the first native headset route is chosen.

## Translation rule

Keep translating intention, not gesture.

Examples:

- mobile drag -> native gaze drift or hand drag
- long press route open -> dwell + pinch or hold + hand pose
- phone light incidence -> room light estimate + local direction
- touch energy -> pinch / grab confidence
