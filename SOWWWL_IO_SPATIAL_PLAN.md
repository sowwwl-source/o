# SOWWWL IO Spatial Plan

This note keeps the `sowwwl.io` track concrete without putting it in production yet.

## Goal

`sowwwl.io` should serve the same project universe as `sowwwl.com`, but as a spatial client:

- same identities
- same `0wlslw0`, `Signal`, `Str3m`, `map`, plasma logic
- different presentation layer
- headset-first interaction model

The short version:

- `sowwwl.com` = entry, product, messaging, public flow
- `sowwwl.xyz` = membrane surface tied to phone sensors
- `sowwwl.io` = spatial surface for headset use

## Platform truth

As of 2026-05-19:

- Apple Vision Pro supports spatial web and WebXR VR flows in Safari, but not a production-grade `immersive-ar` WebXR passthrough flow.
- Apple Vision Pro does support strong native spatial experiences through SwiftUI, RealityKit, ARKit services, and USDZ / Quick Look.
- Meta Quest supports WebXR in browser, but the deeper passthrough camera access that matters for robust MR is a native path.

So `sowwwl.io` should be designed in two layers:

1. a spatial web shell we can test fast
2. native headset clients when we need true passthrough, anchoring, and richer input

## What the current repo already gives us

Reusable now:

- host-aware app variants in `config.php`
- the membrane/camera/orientation/wake/microphone stack in `index.php` and `main.js`
- the torus visual language
- guide and routing logic in `0wlslw0`
- the same backend endpoints, sessions, lands, Signal, Echo, map, Str3m

Not reusable as-is for headset-grade AR:

- phone-specific motion assumptions
- direct reliance on mobile browser camera + orientation semantics
- flat 2D page composition for immersive placement

## Product shape

### Phase 0

Keep everything local only.

- no DNS cutover
- no deploy work
- preview through `localhost` using `?surface=io`

Useful preview URLs:

- `/?surface=io`
- `/0wlslw0?surface=io`

### Phase 1

Build a web spatial shell for `sowwwl.io`.

Target:

- same torus language
- same public/private routes
- softer windowed spatial experience
- gaze, pinch, hand, pointer, and controller aware UI states

This phase is for:

- content framing
- surface grammar
- route simplification
- headset-safe typography and spacing
- deciding what must stay 2D and what can become volumetric

### Phase 2

Build a native visionOS client.

Use it for:

- real spatial placement
- room-aware anchoring
- hand tracking
- world tracking
- persistent volumetric UI
- headset-comfort tuning

Likely split:

- SwiftUI window layer for `0wlslw0`, `Signal`, `Str3m`
- RealityKit layer for torus, landmarks, spatial gestures, volumetric map states

### Phase 3

Build a native Meta client if we want true mixed reality parity there.

Use it for:

- passthrough-aware membrane
- Quest-specific input
- spatial persistence and hand flows
- CV-heavy experiments if needed later

## Data contract

The spatial client should not invent a second product model.

It should consume the same concepts:

- land
- signal thread
- echo session
- str3m items
- map points
- plasma events
- `0wlslw0` prompts and routing decisions

That keeps `sowwwl.io` as a client of O., not a side project.

## Interaction translation

Current web/mobile interaction:

- drag
- swipe
- device orientation
- microphone
- camera texture

Spatial interaction target:

- gaze focus
- pinch select
- hand drag
- dwell / hover states
- volumetric proximity
- optional voice-first mode

The translation rule should be:

- preserve intention, not gesture

Examples:

- swipe to open `Signal` -> gaze card + pinch
- long press to open `0wlslw0` -> hold focus + pinch
- phone orientation bends torus -> head pose / hand pose / ambient room state modulates torus

## Immediate worklist

1. Keep `io` as a host/app variant in the PHP app.
2. Reuse the `xyz` surface as the base preview.
3. Separate spatial copy from phone-sensor copy.
4. Add a headset-safe navigation mode in JS before any deploy.
5. Decide which first route matters most on Vision Pro:
   `0wlslw0`, `map`, or `Str3m`.
6. Only after that, choose whether Phase 2 starts as a native visionOS shell or a deeper web prototype.

## Current local state

Prepared in code:

- `sowwwl.io` is recognized as a future surface variant
- localhost preview supports `?surface=io`
- the shared `xyz` surface can now present `io`-specific copy

Still intentionally not done:

- no Caddy host
- no deploy script change
- no production DNS assumption
- no native client scaffold yet
