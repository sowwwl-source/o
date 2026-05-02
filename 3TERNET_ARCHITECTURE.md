# 3ternet Architecture Note

## Why this note exists

The current live O. stack still lives mainly on a VPS.
That is useful, but incomplete.

The deeper project is not just to host a poetic network.
It is to stop being ripped away from our own data the moment we use the internet.

`3ternet` is the name for that next move:

- the land is no longer only a conceptual anchor
- the land becomes a carried, local, physically present data center
- the public web becomes a ferry, not the vault

This note describes the target architecture without implementing it yet.

## Short thesis

`1ternet` centralizes memory elsewhere.

`3ternet` means:

- the person carries the primary copy of their own land
- public infrastructure helps route, cache, and welcome
- private memory does not have to live permanently in remote infrastructure
- online/offline presence becomes a first-class part of the system

In O., this means:

- `Str3m` and `0wlslw0` can remain globally reachable
- `Signal`, `aZa`, and parts of `Echo` can resolve toward a land that is physically near its holder
- the VPS becomes a ferry and a public threshold, not the center of truth for everything

## Design principles

1. Local first.
The land should work from the body outward, not from the cloud inward.

2. Public by relay, private by possession.
Public discovery can remain mirrored.
Private memory should default to the carried device.

3. Presence is real.
If the necklace sleeps, the land should change state honestly instead of faking permanence.

4. Graceful degradation.
Offline is not failure.
Offline is a different social and technical mode.

5. Clear thresholds.
The visitor must understand what is public, what is delayed, what is private, and what is asleep.

6. Replace extraction with situated exchange.
The infrastructure should minimize unnecessary retention by the VPS or third parties.

## The actors

### 1. Shore node

The public VPS remains the shore node.

It keeps:

- `sowwwl.com`
- `0wlslw0.com`
- public `Str3m`
- onboarding and routing
- public verification and relay logic
- queueing and deferred delivery

It should not keep the full intimate memory of every land forever.

### 2. Pocket land

The carried device is the land in its strongest form.

First practical candidate:

- Raspberry Pi 5 as the core node
- one or two ESP32 / AI Cam companions as sensory satellites
- battery and local storage as the physical memory substrate

This is the `terre de poche`.

It keeps:

- private archives
- direct media traces
- local mailbox state for the land
- identity material and signing keys
- local inference and capture logic when possible

### 3. Visitor clients

These are:

- phones
- tablets
- laptops
- public browsers

They meet O. first through the shore node, then possibly through a live routed passage into a pocket land.

### 4. Sensory satellites

The ESP32 devices are not just cameras or sensors.
They are trace emitters.

They can eventually feed:

- local image capture
- proximity events
- body-worn gestures
- environmental traces
- local-only sensing before publication

## What moves where

### Public shard

Safe to keep or mirror on the VPS:

- public `Str3m` excerpts
- public-facing land metadata
- public entry text
- `0wlslw0` onboarding knowledge
- presence hints that do not expose private location

### Carried shard

Should live primarily on the pocket land:

- private `aZa` archives
- original media
- unpublished drafts
- direct `Echo` material
- mailbox internals
- user secrets and signing keys
- voice history or intimate interaction logs

### Relay shard

May exist temporarily on the shore node:

- encrypted envelopes waiting for a land to wake up
- delivery receipts
- short-lived content caches
- presence tokens
- minimal routing metadata

The relay shard must be small, expiring, and intentional.

## Presence states

This is the key architectural layer.

Every land should expose a truthful state:

### 1. Present

The pocket land is reachable now.

Meaning:

- live routing is possible
- direct requests can tunnel through
- writes may resolve immediately

### 2. Near

The land is not directly open, but a trusted nearby device or session can awaken it.

Meaning:

- the user is likely around
- interaction may resume quickly
- the system can invite a lighter handshake instead of a hard failure

### 3. Asleep

The land is intentionally offline.

Meaning:

- public traces may remain visible
- private access is suspended
- visitors can leave deferred envelopes

### 4. Roaming

The land is moving between networks or reconnecting.

Meaning:

- live routing may be unstable
- writes should prefer queued envelopes
- media-heavy operations should be delayed or compressed

### 5. Lost / split

The pocket land and shore state disagree or the land missed synchronization.

Meaning:

- edits must pause or become read-only
- the user should see a clear reconciliation state
- integrity wins over convenience

## Role of each O. door in 3ternet

### `0wlslw0`

Stays mostly public and shore-based.

Its job becomes:

- orientation
- state explanation
- presence translation
- telling the visitor whether a land is present, asleep, or deferred

`0wlslw0` must explain the system gently:
"this land is asleep, but you can leave an envelope"
is better than a generic error.

### `Str3m`

Remains the most public layer.

It can:

- mirror public traces from lands
- continue existing even if a land goes offline
- provide a sense of place without exposing private storage

### `Signal`

Becomes the most important relay testbed.

It should support:

- direct delivery when the land is present
- deferred encrypted envelopes when absent
- later acknowledgment when the land reconnects

### `aZa`

Becomes the heart of carried memory.

It should evolve toward:

- local-first archive storage
- selective publication
- partial public reading
- explicit separation between mirrored excerpts and the original carried archive

### `Echo`

Eventually becomes land-to-land resonance with minimal central retention.

The shore node may broker the handshake.
It should not become the permanent owner of the exchange.

## Network path

### Phase 1: shore-routed tunnel

The live VPS remains the public HTTPS endpoint.

It routes selected requests to the pocket land through an outbound-initiated secure tunnel.

Good first path:

- Tailscale for speed and operational simplicity

Longer-sovereignty path:

- Headscale / WireGuard mesh controlled by us

The pocket land should never rely on a public inbound port.
It should open outward and maintain the relay path from behind NAT or mobile networks.

### Phase 2: deferred envelopes

When the pocket land is absent:

- the shore node accepts a sealed request
- stores it briefly as an encrypted envelope
- forwards it when the land reappears

This is where O. stops lying about uptime and starts treating absence as structure.

### Phase 3: partial peer flow

Land-to-land interactions can move toward:

- direct encrypted flows when both lands are present
- brokered wake-up through the shore node
- short-lived relay only when direct connection fails

## Security model

### Device identity

Each pocket land needs a durable device identity:

- hardware-bound key pair if possible
- software fallback with secure backup
- public key registered with the shore node

### At-rest secrecy

The carried shard should be encrypted at rest.

Minimum idea:

- encrypted filesystem or encrypted data volume
- local unlock ritual
- backup strategy that does not destroy sovereignty

### End-to-end envelopes

Deferred `Signal` and `Echo` traffic should be sealable:

- encrypted for the target land
- unreadable to the shore node where possible
- signed by the sender land when the sender is authenticated

### Principle of non-collection

The shore node should not keep:

- raw secrets
- private archives
- full voice histories
- long-lived decrypted intimate exchanges

### Voice safety

`0wlslw0` must never become a leakage point.

It should:

- explain states
- route toward legitimate paths
- never collect spoken passwords
- never expose whether a private land is physically located somewhere exact

## Data flows worth building first

### Flow A: public discovery

1. visitor lands on `sowwwl.com`
2. `0wlslw0` explains the threshold
3. visitor reads `Str3m`
4. no pocket land contact needed

### Flow B: deferred message to an asleep land

1. visitor or linked land writes through `Signal`
2. shore node detects target land is asleep
3. interface says so clearly
4. message is sealed and queued
5. pocket land reconnects
6. queue drains
7. acknowledgement returns to shore node

### Flow C: live archive access

1. authenticated user opens `aZa`
2. shore node sees pocket land is present
3. request tunnels through
4. archive preview or retrieval happens from the carried shard
5. optional public excerpt may still be mirrored

### Flow D: local trace capture

1. ESP32 / AI cam captures event
2. data lands locally on the Pi
3. local process decides:
   - private only
   - staged for publication
   - public excerpt
4. shore node receives only what needs to be surfaced

## Hardware roles

### Raspberry Pi 5

Best seen as:

- land core
- encrypted archive
- local router for the wearable cluster
- sync and tunnel endpoint
- optional local inference node

### ESP32 + AI Cam

Best seen as:

- low-power sentinels
- image or event acquisition points
- sensors that feed the land
- not the final source of truth

### Phone

Even if not philosophically central, the phone may remain practically useful for:

- tethering
- local admin
- emergency wake-up
- relay UI

## UX consequences

The interface must learn to say:

- this land is present
- this land is asleep
- this route will be delayed
- this trace is public
- this archive remains carried

The wrong move would be pretending everything is instant and central.
The right move is making partial presence feel natural and dignified.

That means:

- better status language
- no fake spinners for unreachable lands
- explicit deferred delivery moments
- a visual grammar for awake / near / asleep / roaming

## Proposed phases

### Phase 0: current state

- VPS-hosted live O.
- MySQL-backed `Signal`
- local voice + optional upstream guide relay
- public and private logic still mostly central

### Phase 1: tunnel experiment

Goal:

- prove that a live request can safely travel from `sowwwl.com` to a mobile Pi 5

Do first with:

- one land
- one tunneled route
- one read operation

Good first candidate:

- `aZa` status or a tiny private archive preview

### Phase 2: presence protocol

Goal:

- add the land states as real application states

Deliverables:

- present / asleep / roaming logic
- clear visitor copy
- queued envelopes in `Signal`

### Phase 3: carried archive

Goal:

- move primary `aZa` storage to the pocket land

Deliverables:

- local archive store
- mirrored public excerpts
- selective publication rules

### Phase 4: land-to-land relay

Goal:

- move parts of `Echo` toward brokered peer exchange

### Phase 5: sensory memory

Goal:

- ingest ESP32 / AI cam traces as first-class local memory

## What not to do

- Do not start by moving everything off the VPS at once.
- Do not start with full peer-to-peer purity before presence states exist.
- Do not hide offline reality behind generic "server error" language.
- Do not let the tunnel become a permanent opaque backdoor.
- Do not confuse decentralization theater with actual data possession.

## The decisive question

The project does not become `3ternet` merely because a Raspberry Pi is involved.

It becomes `3ternet` when:

- the primary copy of the land is truly carried
- the shore node stops pretending to be the center
- absence, delay, and local possession become part of the social design

That is the threshold.

## Recommended next non-code deliverables

1. a presence-state lexicon for the UI
2. a routing matrix: which routes stay on the shore node, which routes tunnel, which routes defer
3. a security note for device identity and envelope encryption
4. a first tunnel experiment plan for one Pi-backed land

This note is the architectural hinge between the current O. and the pocket land future.
