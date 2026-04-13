# Text-to-Video / Image-to-Video Prompt Guide

Use **structured prompt formulas** to improve the quality and artistic expression of AI-generated videos.

## Scenarios

- [Text-to-video API reference](#basic-formula)
- [Image-to-video (first frame) API reference](#image-to-video-formula)
- [Image-to-video (first and last frames) API reference](#image-to-video-formula)
- [Reference-to-video API reference](#reference-to-video-formula)

---

## Prompt Formulas

A prompt describes the visual content and motion in a video. More accurate and detailed descriptions produce higher-quality videos.

---

### Basic Formula

Simple, flexible prompts produce imaginative videos for creative exploration.

**Prompt = Entity + Scene + Motion**

- **Entity:** The main subject of the video. It can be a person, animal, plant, object, or an imaginary object.
- **Scene:** The environment where the entity is located. It includes the background and foreground and can be a real or imaginary setting.
- **Motion:** The specific movement of the entity and other elements in the scene. This can range from a still scene to slight, large, local, or overall movement.

---

### Advanced Formula

Add richer, more detailed descriptions to the basic formula to enhance texture, vividness, and narrative quality.

**Prompt = Entity (description) + Scene (description) + Motion (description) + Aesthetic control + Stylization**

- **Entity description:** Describes the details of the entity's appearance. You can list adjectives or short phrases. For example, "a young Miao girl with black hair in ethnic costume" or "a celestial fairy from another world, dressed in tattered yet magnificent clothes, with a pair of strange wings made of debris from ruins on her back."
- **Scene description:** Describes the details of the entity's environment. You can list adjectives or short phrases.
- **Motion description:** Describes the details of the motion, including its amplitude, speed, and effect. For example, "swaying violently," "moving slowly," or "shattering the glass."
- **Aesthetic control:** Includes light source, lighting environment, shot size, camera angle, lens, and camera movement. For common cinematic terms, see the prompt dictionary below.
- **Stylization:** Describes the visual style of the video. For example, "cyberpunk," "line art illustration," or "wasteland style." For common styles, see the prompt dictionary below.

---

### Image-to-Video Formula

The image defines the entity, scene, and style—describe the desired motion and camera movements.

**Prompt = Motion + Camera movement**

- **Motion description:** Describe the movement of elements in the image, such as people running or animals waving. You can use adjectives such as "quickly" or "slowly" to control the speed and intensity of the motion.
- **Camera movement:** If you have specific requirements for camera movement, you can use prompts such as "camera pushes in" or "camera moves left." If you want the camera to remain still, you can use "fixed camera" to emphasize this.

---

### Sound Formula (Wan 2.5/2.6)

Add descriptions for voices, sound effects, and background music to your prompts for sound control.

**Prompt = Entity + Scene + Motion + Sound description (voice/sound effect/background music)**

**Sound description:** Describes the sound elements in the video and their changes over time. This guides the sound content and atmosphere to match the visual narrative precisely. This includes three main categories: human voice, sound effects, and background music (BGM).

#### Voice Formula

**Voice = Character's lines + Emotion + Tone + Speed + Timbre + Accent**

Example: A man is doing a stand-up comedy routine. He says, **"Study hard and make progress every day,"** in a relaxed tone, at a moderate speed, with a clear voice, in American English.

#### Sound Effect Formula

**Sound effect = Source material + Action + Ambient sound**

Example: A small glass ball falls from a table onto a wooden floor, making a "thud" sound in a quiet indoor environment.

#### Background Music Formula

**Background music = Background music/score + Style**

Example: On a rainy night, a spooky, narrow corridor has a window at the end, with suspenseful background music.

---

### Reference-to-Video Formula (Wan 2.6)

Generate videos by referencing a character (person, cartoon, pet, or prop) from an input video—their appearance, motion, and voice timbre.

**Prompt = Character + Action + Lines + Scene**

- **Character:** Reference the main character using an identifier such as character1. You can reference up to three characters at the same time. Each character can be referenced multiple times in the prompt to precisely control their behavior.
- **Action:** Describe the motion of the character or other elements. This includes static states, changes in expression and emotion, body movements, external actions, and displacement.
- **Lines:** The character's spoken content. This supports a single character speaking or a dialogue between multiple characters.
- **Scene:** The environment where the character is located. It includes the background and foreground and can be a real or imaginary setting.

**Example:**

This is a whimsical fairytale scene. character1 is jumping and playing on the grass. character2 is playing the piano under a nearby apple tree. An apple falls on character2's head. character1 points at character2 and says happily, "You're going to become a scientist!".

*In this example, the reference video for character1 is a rabbit, and the reference video for character2 is a dog. Each reference video contains only a single character.*

---

### Multi-Shot Formula (Wan 2.6)

Generate coherent multi-shot narrative videos with precise control over shot structure, camera position, and duration. Key elements remain consistent across shots.

**Prompt = Overall description + Shot number + Timestamp + Shot content**

- **Overall description:** Briefly summarize the entire video content. State the story's theme, narrative style, main emotion, or core event. This helps the AI grasp the overall narrative direction.
- **Shot number:** Assign a number to each shot to distinguish the sequence of different scenes or segments in the video. This helps organize the video structure clearly.
- **Timestamp:** Specify the time range for each shot in the video. This ensures that the shot content corresponds to the video timeline and improves generation accuracy.
- **Shot content:** Describe the specific behavior of the main character or object in each shot. This includes actions, speech, expressions, and posture. You can follow the general prompt writing style for a single shot.

**Example:**

This story is a short play about giving up and regaining hope, told from a third-person perspective.

- **Shot 1** [0~3 s]: A boy sits alone in a corner of a playground, looking down at a letter in his hand. He sighs softly, his eyes filled with confusion.
- **Shot 2** [4~6 s]: Hard cut transition, fixed camera position, focusing on the boy's eyes, which are glistening with tears, showing loss and helplessness.
- **Shot 3** [7~10 s]: Hard cut transition, the scene changes to a simple classroom. A girl with a gentle and firm gaze, dressed in plain clothes, walks over to the boy with a warm and determined smile to comfort him.

---

## Prompt Optimization Techniques

### 1. Automatic optimization: Enable the prompt_extend parameter

Enable the prompt_extend parameter when calling the API. The system optimizes short, general prompts automatically (e.g., "A kitten plays with a snowball in the snow").

### 2. Generate prompts using prompt formulas

For precise control over image details, style, or complex dynamics, write prompts using structured formulas.

Alternatively, provide formulas as system messages to an LLM like qwen3.6-plus to generate prompts.

#### Example: Use the sound formula to optimize a prompt

```python
import os
from openai import OpenAI

try:
    client = OpenAI(
        # The API keys for the Singapore and Beijing regions are different.
        api_key=os.getenv("DASHSCOPE_API_KEY"),
        base_url="https://dashscope-intl.aliyuncs.com/compatible-mode/v1",
    )

    completion = client.chat.completions.create(
        model="qwen3.6-plus",
        messages=[
            {"role": "system", "content": "You are a prompt optimization assistant. The prompt formula is: Prompt = Entity + Scene + Motion + Sound description (voice/sound effect/background music)\nSound description: Describes the sound elements in the video and their changes over time. It guides the sound content and atmosphere to match the visual narrative precisely. It mainly includes three categories: human voice, sound effects, and BGM.\nVoice = Character's lines + Emotion + Tone + Speed + Timbre + Accent\nExample: A man is doing a stand-up comedy routine. He says, \"Study hard and make progress every day,\" in a relaxed tone, at a moderate speed, with a clear voice, in American English.\nSound effect = Source material + Action + Ambient sound\nExample: A small glass ball falls from a table onto a wooden floor, making a \"thud\" sound in a quiet indoor environment.\nBackground music = Background music/score + Style\nExample: On a rainy night, a spooky, narrow corridor has a window at the end, with suspenseful background music."},
            {"role": "user", "content": "A kitten plays with a snowball in the snow"},
        ],
    )
    print(completion.choices[0].message.content)
except Exception as e:
    print(f"Error message: {e}")
```

**Output example:**

```
Entity: A fluffy kitten
Scene: A snow-covered yard on a winter morning. The snow is thick and soft. There are frosted branches and a small snowman in the distance.
Motion: The kitten rolls up a snowball with its front paws, clumsily pushing it forward. Suddenly, the snowball breaks apart, and the kitten jumps back in surprise. It then curiously approaches, sneezes, does a somersault, and continues to play by nudging the snowball with its head.
Sound description:
- Voice: None
- Sound effects: The soft "crunch, crunch" of the kitten's paws on the snow; the "swish" of the snowball rolling; the "crack" as the snowball breaks; the kitten's cute, crisp "achoo!" sneeze; the "rustle" of snow falling as it tumbles.
- Background music: A light and warm piano score with a nursery rhyme style. The rhythm is playful, like a small animal frolicking, creating a whimsical winter atmosphere.

Full prompt:
A fluffy kitten plays with a snowball in a snowy yard on a winter morning. It rolls up a snowball with its front paws, clumsily pushing it forward. The snowball suddenly breaks, and the kitten jumps back in surprise, sneezes, then curiously approaches and finally plays by nudging the snowball with its head and tumbling around. Sound effects include the "crunch" of paws on snow, the "swish" of the rolling snowball, the "crack" of it breaking, the kitten's "achoo!" sneeze, and the "rustle" of falling snow. The background music is a light, warm, nursery-rhyme-style piano score, creating a warm and adorable winter atmosphere.
```

---

## Sound Generation

To generate videos with synchronized audio, describe human voices, ambient sounds, and other elements that match the video's visuals.

> The following video effects were all generated using Wan 2.5 Preview.

### Voice

| Type | Prompt example | Video effect |
|------|---------------|--------------|
| Single speaker | Single speaker, low angle shot, wide shot, daylight from large window, soft lighting, warm colors, center composition. A classical-faced female model with voluminous, flowing auburn hair is framed within an oversized ornate golden picture frame adorned with fresh yellow wildflowers such as rapeseed blooms. She wears a pale yellow dress embellished with shimmering circular yellow sequins and translucent lace long gloves. Her right hand rests on the edge of the frame while her left hand extends forward, fingertips pressing against an invisible barrier, creating a subtle tension in her fingers and forearm. The background inside the frame dissolves into a hazy, sky-like mist. Captured with a wide-angle lens that curves the perspective slightly, the foreground wildflowers blur at the edges, enhancing the surreal effect of the figure emerging from or trapped within the artwork. Sunlight streams through a high window, casting soft, even illumination across her face and the gilded frame, highlighting the texture of petals and fabric. Ambient audio: gentle orchestral strings swell faintly in the background. She whispers, "Is this freedom... or just another kind of frame?" | [videos/01.mp4](videos/01.mp4) |
| Group conversation | Group conversation, warm colors, daylight from large window, soft lighting, medium shot, center composition. A vintage wood-paneled dining room features dark wooden walls and a large window revealing lush green plants outside. Sunlight streams in through the window, casting gentle side lighting across the scene. The dining table is laden with roasted ham, fresh bread, grapes, silver cutlery, and a gleaming tea pot. A Caucasian man on the left wears a dark suit with a white shirt and black vest, his expression serious and focused as he speaks deliberately, forming clear lip movements. Across from him, a Caucasian woman with short brown hair sits in a dark dress adorned with delicate floral patterns, her calm face tinged with melancholy, eyes slightly downcast in quiet contemplation. Their hands rest lightly on the table, unmoving. The man speaks in a low tone: "We can't keep pretending everything is unchanged." The woman exhales softly, then murmurs, "But what if forgetting hurts more than remembering?" | [videos/02.mp4](videos/02.mp4) |
| Timbre | Moonlight, soft lighting, side lighting, medium close-up shot, center composition. Inside a cozy children's bedroom, pale silver moonlight streams through the window, casting gentle shadows across the wooden floor. A young Caucasian girl, around 6 years old, sits curled up against a fluffy pillow, wearing a pastel pink pajama set with tiny star patterns. She tightly embraces an oversized brown teddy bear, her small face buried momentarily in its soft fur before she pulls back slightly to whisper with innocent tenderness: "Don't be afraid, I'll protect you." Her wide eyes glisten faintly in the dim light, filled with sincerity and quiet courage. The camera holds steady at eye level, capturing the delicate movement of her lips as she speaks again, voice barely above a breath: "I'm here." Faint ambient sounds of a creaking house and distant crickets fill the silence, while a soft wind rustles the sheer curtains near the window. | [videos/03.mp4](videos/03.mp4) |
| Singing | Surreal style, medium full shot, daylight, clear sky light, soft lighting, side lighting, warm tones, center composition. Two young Caucasian female models are situated in a lush, rolling green grassland, set against a backdrop of a pure blue sky adorned with fluffy, picturesque white clouds. In the foreground, a woman in her twenties, with fair, freckled skin, wears a sky-blue knitted vest. Her slightly curly brown hair is wrapped in a light blue headscarf. She lies on her side on the soft grass, her right hand gently supporting her cheek, and her left arm naturally extended. Her clear eyes look directly at the camera, a slight smile playing on her lips. A gentle breeze brushes her hair and clothing. Behind her, another young woman leans against a giant, cartoon-style pink mushroom with white dots. A burgundy crocheted headband encircles her hair, and she wears a yellow camisole paired with a flowing white long skirt, its hem gently swaying in the wind. Her eyes are closed, her expression serene and content, as if deeply immersed in a sunlit dream. The mushroom's surface is smooth and saturated in color, resembling a prop from a fairy tale. The scene is accompanied by gentle folk guitar music. The foreground woman softly hums: "Follow the light, where the wild flowers grow," her voice clear and soft. In the background, bird calls and the distant rustling of leaves filter through, adding to the sense of natural tranquility. | [videos/04.mp4](videos/04.mp4) |

### Sound Effects

| Type | Prompt example | Video effect |
|------|---------------|--------------|
| Footsteps | Steampunk style, dusk, mixed light (gaslight and neon intertwined), side light, cool tones with an orange-red glow, medium full shot, center composition. The camera pushes in slowly, revealing a dark, narrow railway alley in a Victorian city. The bluestone pavement reflects a damp glimmer, and thick fog slowly flows, winding around rusty copper pipes and steam valves. A group of retro-styled robots marches through, emitting small puffs of steam from their joints. Footsteps, the rhythmic "clatter" of metal feet hitting the ground. In the deep background, a giant gear-shaped building rises, its facade covered with intersecting transmission rods and pressure gauges. A radio tower stands on top, continuously emitting pale blue electric arcs. The neon sign "WAN" flickers in the fog, accompanied by a low-frequency electrical hum. | [videos/05.mp4](videos/05.mp4) |
| Knocking | Low angle, medium shot, balanced composition. Cool tones, dim lighting, with only a faint side backlight from a distant streetlamp. A lonely private investigator, a middle-aged Caucasian man, wears a trench coat with the collar turned up and a fedora that hides his sharp, wary eyes. He stands at the end of a narrow, dark alley, in front of a weathered, peeling wooden door with faded gray-green paint. Rain-soaked cobblestones glisten under the dim, swaying light from the alley entrance. The detective raises his right hand and knocks on the door with the knuckle of his index finger in a precise rhythm: two light taps, followed by a heavy one, repeated three times. After each set of knocks, he pauses, tilting his head slightly to listen, his jaw tight and his expression serious. | [videos/06.mp4](videos/06.mp4) |
| Object falling | Top-down angle, top shot, soft light, daylight, center composition, cool tones. In a medium close-up shot, a hand holds a white ping-pong ball between the thumb and index finger over the center of a wooden table. The ball is released and falls vertically under gravity. It hits the tabletop with a crisp tap, then bounces back up to a height lower than its starting point. This action repeats: each bounce has slightly less force, accompanied by a progressively fainter crisp tapping sound. The background consists of blurred indoor elements-the faint describes of a desk lamp and a bookshelf. As the process continues, the ball's vertical motion gradually diminishes until it finally comes to rest on the table. A rhythmic tapping sound is precisely synchronized with each bounce. | [videos/07.mp4](videos/07.mp4) |
| Impact sound | Low angle, medium close-up shot, daylight, soft light, center composition, cool tones. A small red toy car is parked at the top of a smooth cardboard ramp tilted at 30 degrees. Its surface reflects a slight sheen under the diffused indoor light. The end of the ramp abruptly stops at a vertical white wall. After being released, the toy car begins to slide down slowly, accelerating smoothly along the ramp. Its plastic wheels roll silently on the smooth surface of the cardboard. Upon reaching the bottom, the toy car's front bumper hits the wall with a sharp tap, producing a short, crisp impact sound. The collision causes the front hood to dent slightly, just enough to show damage. The momentum reverses, and the toy car bounces backward, rolling a short distance of about 20 cm before finally stopping on the flat ground. | [videos/08.mp4](videos/08.mp4) |
| Fire burning | Close-up, side light, daylight, cool tones transitioning to warm tones, center composition, soft light. A block of pure, flawless ice occupies the center of the frame, with tiny air bubbles frozen inside and frost condensed on its surface, crystal clear. Suddenly, a flame erupts from the center of the ice core. The orange-red light gradually spreads. The ice does not melt but transforms into hot charcoal and lava textures as if through a material conversion. The flame consumes the ice from the inside out, turning from transparent to opaque, with black charcoal and crimson intertwined, sparks flying, and light and shadow dancing. Finally, the entire block of ice turns into a blazing fire, still maintaining its original cubic shape. The background is a deep blue sky with a gentle breeze, and fine frost dust floats in the air. The crackling sound of the fire burning is mixed with a low hum. | [videos/09.mp4](videos/09.mp4) |
| Game sound effects | 8-bit pixel style, top-down view. The screen shows a maze-like dungeon shrouded in darkness. Only a bright pixelated light circle around a silver-armored knight illuminates a certain area, as if the light source is a faint magic lamp hovering above his head, creating a center composition. The knight, dressed in shiny silver armor and holding a longsword, moves forward steadily. Each step is a frame-by-frame animation, and the ground shakes slightly as his feet land. As he moves through the narrow passages, the ink-like darkness is dispelled frame by frame, and new paths and rooms appear with a pixelated fade-in effect. When he steps on a hidden pressure plate, a distant stone wall makes a "clank" sound effect and slowly slides open with pixel-style mechanical animation, revealing a secret room with a enormous treasure chest. The background sound is a low, looping 8-bit adventure music, interspersed with occasional dripping water and echoing footsteps. The moment the treasure chest opens, a golden light bursts out, accompanied by a crisp "ding!" sound effect. | [videos/10.mp4](videos/10.mp4) |
| Electronic sound effects | Glitch sound, VHS glitch aesthetic, CRT scanline effect, tilted angle, medium shot, symmetrical composition. The lighting is a soft mix of neon and screen light, with warm tones. A cyber dancer emerges from 80s retro VHS noise, in a dimly lit underground arcade surrounded by old game machines and flickering monitors. This humanoid creature has chrome limbs, glowing red optical sensors, and exposed mechanical joints that flicker with electric blue light. As the beat starts, it begins a series of precise and stiff movements-arms suddenly folding into sharp angles, head rotating 180 degrees, one leg unnaturally extending forward-its movements mimicking the stuttering effect of an analog tape. Each movement triggers a visual glitch transition: the image distorts into rolling snow noise, then switches to the next pose through a horizontal CRT scanline wipe. Behind it, neon pink and cyan lights reflect off the polished metal surfaces. The audio is retro synth-wave music with a steady electronic pulse. Background sound effects include the low hum of old electronic devices, occasional clicks of a tape rewinding, and distorted radio chatter in the distance. | [videos/11.mp4](videos/11.mp4) |
| ASMR | ASMR, a black knife moves from right to left, cutting into a white, fluffy, cloud-like object placed on a light-colored wooden cutting board. The cloud deforms upon contact with the knife, its surface splitting, and a part of its structure is cut off and displaced. Light smoke continuously rises from the edges of the cloud. After the cut is complete, the knife stops, and the cloud is divided into two parts, with one side collapsing. The process is accompanied by a slight 'hiss' and the sound of airflow from sublimating dry ice. | [videos/12.mp4](videos/12.mp4) |
| Animal sounds | Backlight, soft light, wide-angle lens, center composition. In the frame, a middle-aged bald man wearing a well-tailored dark charcoal long coat walks slowly in a vast, open wilderness with his back to the camera. The hem of his coat flutters in the wind. He is surrounded by a large flock of plump, pure white sheep. They part smoothly in front of him and close behind him like a living river. The dry, earth-toned grass slopes gently under a pale blue sky, stretching to the horizon. The gloomy daylight is evenly scattered, enveloping the entire scene. The air is quiet, with only the slight rustle of the sheep's wool as they move and the sound of bleating in the distance. As he moves forward, a sheep on his left lifts its head and lets out a soft "baa". | [videos/13.mp4](videos/13.mp4) |
| Keyboard sounds | A hand presses keys on a keyboard made of various cookies and candies. Each time a fingertip lands, the corresponding cookie keycap slightly depresses and then quickly springs back, simultaneously producing a crisp 'click-clack' sound that combines the crispness of a cookie with the sharp feedback of a mechanical keyboard. The surface of the coffee ripples slightly, and the light and shadow on the table and cup sway gently. The coffee cup occasionally makes a very faint 'gurgle' sound. The fingers continue to press different keycaps in a continuous, steady rhythm. | [videos/14.mp4](videos/14.mp4) |

### Background Music

| Type | Prompt example | Video effect |
|------|---------------|--------------|
| Natural environment | Daylight, warm tones, side light, close-up shot, center composition. A young woman with wavy chestnut hair, wearing a light blue slip dress with delicate lace trim, stands gracefully in a sun-drenched garden. Her slender fingertips gently pinch the edge of a pink rose petal and slowly pluck it from the flower in a smooth, unhurried motion. The camera focuses closely on her hand and the flower, capturing every subtle movement-the slight tremor of her fingertips, the gentle separation of the petal from the stem. Her face is softly out of focus, her eyes lowered, her expression serene and focused, her lips slightly parted. Behind her, a lush field of blooming flowers-peonies, daisies, and lavender-blurs into a gradient of green and soft colors. The sunlight casts soft highlights on her skin and the fabric of her dress, creating a natural glow. There is no dialogue or human voice; ambient sounds include the gentle rustling of leaves, the chirping of distant birds, and the sound of the breeze blowing through the petals. | [videos/15.mp4](videos/15.mp4) |
| Urban environment | Tilt-shift lens, medium shot, balanced composition. A Chicago "L" train glides smoothly on elevated tracks, weaving through a dense urban canyon. Morning sunlight pours down from the sky, bringing a soft, directional light to the scene that highlights the metallic sheen of the train cars while casting a gentle shadow over the adjacent skyscrapers. The train remains in sharp focus, like a meticulously crafted miniature model, with the wheels clattering on the tracks as it navigates a curved section, gradually approaching a station. The passengers inside the cars are blurred into soft shapes with faint motion trails; the surrounding buildings are blurred into a dreamlike bokeh, their windows shimmering with warm and cool tones. The camera pans horizontally at track level, creating a seamless tracking motion that enhances the visual illusion of a miniature world. Background sound effects include the rhythmic rumble of the train, the distant hum of the city, the occasional screech of brakes, and muffled announcements from the public address system. | [videos/16.mp4](videos/16.mp4) |
| Specific space | Rim light, backlight, low contrast, cool tones, low saturation, medium full shot, long-focus lens. Against a space background, an astronaut in a white spacesuit floats slowly in the deep blackness of space. The helmet's surface reflects the faint light of distant stars, and their calm face is vaguely visible inside the visor. They are holding an old film camera steady with their right hand and gently adjusting the focus ring with their left. They press the shutter, aiming at the Earth, with a steady and focused motion. The Earth hangs in the upper right of the frame, a complete sphere wrapped in a blue atmosphere, glowing softly in the darkness. The camera pushes in slightly, and the astronaut's body leans back slightly with the force of the push, their posture weightless and natural. In the background, the Milky Way stretches across the void like a misty band of light, and a few stars twinkle slowly. A meteor streaks by in the distance, leaving a brief trail of light. Background sound content: very low-frequency cosmic background radiation white noise, with intermittent, slight "click" deformation sounds from the spacecraft's metal structure due to temperature differences. | [videos/17.mp4](videos/17.mp4) |

### Music Types

| Type | Prompt example | Video effect |
|------|---------------|--------------|
| Emotional music | Felt style, soft light, daylight, medium shot, center composition. Warm and happy atmospheric music. A whimsical, rainbow-colored yarn bridge spans across a fabric landscape, its strands slowly winding and unwinding in a rhythmic motion. The bridge is made of thick, felted wool with stitched seams, and each arch seems to pulsate gently as if alive. A small car decorated with buttons, with wheels sewn from black thread and oval plastic buttons for headlights, drives smoothly across the bridge. As the car passes, tiny spring-like passengers made of coiled red and yellow yarn pop up from hidden crevices in the bridge, bouncing gently a few times. Each spring passenger has two black bead eyes and a stitched smile. The background is a quilted cotton sky, adorned with embroidered clouds and hand-sewn stars. The soundtrack is soft acoustic guitar music and the subtle rustle of fabric. Whenever a spring passenger pops up, it is followed by a gentle giggle: "Eee!" The camera remains still, capturing the clear details of the entire scene. | [videos/18.mp4](videos/18.mp4) |
| Beat-synced music | Beat-synced music, disco ball reflected light, artificial light, soft light, side light, warm tones, medium shot, center composition, low-angle shot. An anthropomorphic character with a cat head, part calico and part white, stands in the center of a yellow stage. He wears a well-tailored white suit with black circular patterns on the lapels and fluffy white gloves on his hands. He raises his right arm and bends his left elbow to the rhythm, his legs crossed in a classic 70s dance pose. The cat's eyes are bright, its whiskers twitch slightly, and its ears twitch to the beat. Above, multiple rotating disco balls scatter light spots throughout the space. The background is a black-and-white checkerboard wall and giant vintage speakers. The floor is a dance floor made of red, blue, and purple panels that extends into the distance. As the funk music starts, with a clear drum beat and a groovy bassline, he hums: "I'm the king of the night, groove under the light, meow like a cat but I dance like a man-yeah!" As the light and shadow flow, colored light spots dance on the surface of his suit. The background sound is a mix of crowd cheers and retro synthesizer melodies. | [videos/19.mp4](videos/19.mp4) |
| Light music | Stop-motion animation, medium shot, symmetrical composition. The lighting is a mix of cool and warm tones, with the light source coming from real paper lanterns and moonlight filtering through translucent rice paper, supplemented by soft side light. The entire scene is a minimalist two-dimensional world made of multiple layers of paper. Below, blue and green folded paper waves ripple gently. A small, delicate white origami boat with clear creases moves steadily through the shifting shadows. Suddenly, black paper-cut tentacles slowly extend from the depths, and a giant paper octopus appears, its matte surface seeming to swallow the light. Then, under the soft moonlight filtering through a translucent vellum sky, schools of glowing paper jellyfish float rhythmically, their edges cleverly backlit. The background music is a quiet piano melody, occasionally accompanied by crisp chimes, together creating a serene and magical atmosphere. | [videos/20.mp4](videos/20.mp4) |

---

## Prompt Dictionary

### Light Source

| Type | Prompt example | Video effect |
|------|---------------|--------------|
| Daylight | Rim light, low contrast, medium close-up, daylight, left-heavy composition, clean single shot, warm tones, soft light, clear sky light, side light, daytime. A young girl sits in a field of tall grass with two fluffy donkeys standing behind her. The girl is about 11 or 12 years old, wearing a simple floral dress with her hair in two braids, and has an innocent smile on her face. She sits cross-legged, gently touching the wildflowers beside her. The donkeys are sturdy, with their ears pricked up, curiously looking toward the camera. Sunlight bathes the field, creating a warm and natural scene. | [videos/21.mp4](videos/21.mp4) |
| Firelight | Firelight, over-the-shoulder shot. A man in a white shirt and brown vest stands in front of a fireplace, looking at a person to the right of the camera. | [videos/22.mp4](videos/22.mp4) |
| Overcast light | Medium-focus, soft light, low contrast, rim light, low-angle shot, overcast light, low saturation, medium close-up, clean single shot, cool tones, center composition. A low-angle shot captures a close-up of a man outdoors. He is wearing black clothes, a gray sweater, a white shirt, and a black tie. He looks at the camera and moves forward. Behind him is a brown building with windows that have yellow lights inside. In front is a black door. The camera moves forward. On the right side of the camera, a blurred black object sways back and forth. The background is black. | [videos/23.mp4](videos/23.mp4) |
| Clear sky light | Sunset, warm tones, medium shot, low saturation, daylight, side light, clear sky light. In a kitchen, a Caucasian man is preparing food. In a medium close-up, eye-level shot, he is wearing a white shirt and a black tie, standing at a table with a blue cup, a jar of sugar, and other condiment bottles. He scoops some sugar from the jar into the cup. The blurred background is a wall with floral wallpaper, on which hangs a white cabinet filled with various items. Sunlight streams in through the window. | [videos/24.mp4](videos/24.mp4) |

### Lighting Environment

| Type | Prompt example | Video effect |
|------|---------------|--------------|
| Soft light | Sunset, soft light, side light, rim light, warm tones, low saturation, center composition, medium close-up, eye-level shot. A couple stands next to a yellow taxi in a half-length shot. The man wears a beige trench coat with the collar slightly raised, his hands in his pockets, smiling, and leaning slightly forward. The woman wears a green beret with a matching dress and lace gloves, elegantly linking arms with the man and leaning slightly toward him. In the background, the silhouettes of retro-style buildings are faintly visible. Two green streetlights cast a warm glow. In the distance, a police officer in a classic uniform stands straight, enhancing the realism of the urban scene. Light comes from the right side of the frame, creating a soft rim light on the characters' silhouettes. The overall tone is golden-yellow, creating a natural and warm atmosphere. | [videos/25.mp4](videos/25.mp4) |
| Hard light | Hard light, side light, medium shot, low saturation, high contrast, medium-focus, eye-level shot of a man's close-up. He is wearing a striped shirt and sitting at a wooden table with some folders and a red telephone on it. His arms are crossed over his chest, and his head is resting against the wall. His eyes are closed, and his mouth is slightly open as he speaks. In the foreground, there is a blurred figure. The background is a dark wooden wall with a painting and other decorations. The whole scene gives a quiet, contemplative feeling. | [videos/26.mp4](videos/26.mp4) |
| Side light | Rim light, side light, soft light, medium close-up, dusk, sunset, center composition, warm tones, low saturation, long-focus lens. A woman with fluffy brown curly hair stands elegantly in front of a magnificent stained-glass window. She wears a flowing white long dress, her hair neatly combed back. Her soft facial contours are gently illuminated by the colorful light filtering through the window. The woman is talking to someone off-screen, but a hint of sadness flashes in her eyes, adding a layer of depth to her mysterious temperament. The background is dim, with strong light and shadow contrast, further highlighting the tension of the character's emotions. The stained glass casts colorful light and shadow under the setting sun, enhancing the artistic and atmospheric feel of the overall image. | [videos/27.mp4](videos/27.mp4) |
| High contrast | High contrast, high saturation, short-side composition, sunset, medium-focus, soft light, backlight, warm tones, rim light, medium close-up, daylight, clear sky light. A close-up of a Caucasian woman wearing a yellow plaid dress and earrings. As the low-angle camera rises, the woman looks up, her eyes filled with tears, looking ahead and speaking. In the blurred background is a white brick wall with a painting on it. Below is a wooden cabinet with a vase and a desk lamp, and next to it is a chair. On the left is a brown cabinet with a desk lamp on it, and behind it is an open door with sunlight shining in from outside. | [videos/28.mp4](videos/28.mp4) |

### Lighting Time

| Type | Prompt example | Video effect |
|------|---------------|--------------|
| Daytime | Sunrise, daytime, soft light, side light, rim light, medium close-up, center composition, warm tones. A Caucasian woman sits on a large white bed, wearing a blue and white plaid cotton top. Her hair is messy and fluffy, with natural golden-brown waves. Her face shows a slight tiredness, and she looks softly toward the camera, as if in conversation. Sunlight streams into the room through light beige curtains, casting soft light and shadow on the bedsheets and walls. White iron railings surround the window, enhancing the quiet and warm indoor atmosphere. The camera focuses on the woman's upper body, with the background blurred to highlight her emotions and the subtle interaction with the environment. | [videos/29.mp4](videos/29.mp4) |
| Night | Practical light, high saturation, mixed light, night, top light, soft light, full shot, symmetrical composition, wide-angle. In dim lighting, an eye-level shot captures a full view of two people sitting at a table talking. The man on the left is wearing a green sweater and white pants, looking intently at the woman on the right. The woman is wearing a striped shirt and blue jeans, holding a book or folder. Their surroundings are filled with a retro atmosphere, with old-fashioned furniture, a chandelier, and curtains in the background. There are also some items on the table. | [videos/30.mp4](videos/30.mp4) |
| Dawn | Dawn, firelight, left-heavy composition, cool tones, low contrast, top-down shot. In the video, the camera slowly pushes in, focusing on the archer's eyes and arrow. The archer is wearing outdoor clothing, their eyes focused. As their fingers release the bowstring, the arrow quickly leaves the bow. The camera continues to push in, capturing the focused expression on the archer's face and the precise moment the arrow begins to move. The background is a simple outdoor scene with trees and grass. | [videos/31.mp4](videos/31.mp4) |

### Shot Size

| Type | Prompt example | Video effect |
|------|---------------|--------------|
| Close-up | An eye-level close-up of a man's face. He has blue eyes and a bald head. His face is covered in sweat. He stares at the camera, then lowers his head. White English subtitles "My little Max..." appear at the bottom of the screen. The background is blurred and indistinct. The camera moves up for a top-down close-up of the back of the man's head. | [videos/32.mp4](videos/32.mp4) |
| Close shot | Rim light, close shot, daylight, soft light, low saturation, center composition, daytime. In an eye-level shot, three figures compose the frame. In the center is a boy in a red school uniform, about 15 or 16 years old, with slightly curly blond hair, defined features, and a focused expression. He first looks to the left, then quickly turns his head to the right, and then looks back to the left, his lips opening and closing as if in conversation. His movements are natural and smooth, his eyes changing with the turn of his head. On the right is the blurred face of a woman, only half of her face is visible, about in her thirties, her expression indistinct. The background is a classroom environment, with the walls covered in black and white photo frames. The figures of several students in red school uniforms are faintly visible. In the foreground, a blurred figure quickly passes in front of the frame, creating a sense of dynamism. The lighting is soft and even, and the overall tone is neutral, highlighting the frame's layers and details. | [videos/33.mp4](videos/33.mp4) |
| Wide-angle | Center composition, wide-angle, extreme full shot, backlight, establishing shot, practical light, warm tones. In a sunlit Roman square, actors in togas argue around a marble table. The camera slowly moves to the right, capturing their intense discussion and gestures. In the background, a horse-drawn carriage slowly passes over the cobblestone street, making a creaking sound. A historian's voiceover narrates the political tensions of the time, blending history with a modern perspective. The actors in the frame have serious expressions and natural movements, as if it were a real historical reenactment. | [videos/34.mp4](videos/34.mp4) |

### Shot Composition

| Type | Prompt example | Video effect |
|------|---------------|--------------|
| Center composition | Soft light, warm hue, medium close-up, center composition, dusk, clear daylight, rim light. The camera slowly pushes in for a close-up on the face of a distinguished elderly gentleman. He has striking, long, silver-white hair and a well-groomed, flowing beard. He wears a tailored dark suit with a matching tie. He looks elegant. Soft, warm light envelopes his face. It creates a gentle rim light, like dusk sunlight through a window. His expression is pensive. He gazes into the distance, lost in thought or memory. The background is blurred. This highlights his weathered, wise, and calm face. Every wrinkle seems to tell an untold story. | [videos/35.mp4](videos/35.mp4) |
| Right/Left-weighted composition | Over-the-shoulder shot, close-up, medium focal length, soft light, low contrast, left-weighted composition, overcast light, clean single shot. A straight-on close-up shows a woman walking in an outdoor garden. She wears a light-colored outfit. Her hair is in a netted bun. Her expression is focused and pensive. She looks into the distance. Her head turns slightly, as if observing her surroundings. The background is a manicured garden with neat hedges and a distant sculpture. The scene feels calm and peaceful, perhaps at a country estate. | [videos/36.mp4](videos/36.mp4) |

### Lens

| Size | Prompt example | Video effect |
|------|---------------|--------------|
| Long-focus | Close shot, soft light, daylight, side light, clear sky light, clean single shot, long-focus lens, cool tones, center composition. An eye-level close-up of a man's face. He is wearing a black suit and a white shirt with a red tie. His hair is graying, and his eyes are deep-set, with two wrinkles in the eye sockets. His lips are tightly closed, his expression serious and focused. The background is blurry, but it can be seen that it is an indoor environment, possibly an office or a conference room. There is a window behind the man, and the outside world can be seen through the glass. | [videos/37.mp4](videos/37.mp4) |
| Ultra-wide-angle fisheye | Balanced composition, top light, warm tones, low contrast, ultra-wide-angle fisheye, group shot. A group of Caucasian men and women gather around a circular hole in a white wall, looking down. In a close-up, low-angle shot, their expressions vary, showing surprise, curiosity, and excitement. One of them is holding a brown paper bag and opening it. He is wearing black clothes and has golden hair. The others are gathered around him, their faces illuminated by the light, appearing very clear. The wall in the background is white, creating a sharp contrast with the people. | [videos/38.mp4](videos/38.mp4) |

### Camera Angle

| Type | Prompt example | Video effect |
|------|---------------|--------------|
| Over-the-shoulder angle | Two-shot, long-focus, warm tones, over-the-shoulder shot, high contrast, soft light, daylight, daytime, close shot, center composition. A girl sits by a window. In an eye-level view, she is wearing a red and black striped sweater with a blue turtleneck underneath. Her short golden hair hangs smoothly behind her ears. She looks gently at the person off-camera with a slight smile, her expression natural and friendly. Her hands are gently folded on the table, where an open book and a white ceramic cup with steam rising from it are placed. The background is blurred, with soft daylight streaming in through the window. Faint warm yellow lights and scattered red decorations are visible, creating a cozy atmosphere. | [videos/39.mp4](videos/39.mp4) |
| High-angle | High-angle shot, daylight, ultra-wide-angle fisheye, clean single shot, mixed tones, daytime. A top-down close-up of a man sitting in the back seat of an orange taxi. He is wearing a black jacket and a gray sweater. His gaze is directed out the car window, his expression serious and thoughtful. The taxi is in motion, and the background is a city street where other vehicles and buildings can be seen. The man has a silver stand with two handles on his lap. His hands are on his knees, his fingers lightly tapping them. | [videos/40.mp4](videos/40.mp4) |
| Aerial shot | Warm tones, extreme full shot, aerial shot, clear sky light, hard light, daytime, establishing shot. In a barren desert, a black SUV is speeding down a highway. In a top-down shot, the vehicle is driving on the left side of the road. It has a luggage rack and a red taillight on the roof. The camera slowly pushes forward. In front of the vehicle is a vast expanse of yellow sand dunes, with a few mountain peaks visible in the distance. The sky is pale blue, and sunlight shines through the clouds, bringing a touch of warmth to this desolate land. The sides of the road are dry grassland, dotted with some low shrubs. | [videos/41.mp4](videos/41.mp4) |

### Camera Movement

| Type | Prompt example | Video effect |
|------|---------------|--------------|
| Clean single shot | Close shot, top-down angle shot, top light, soft light, dawn, low contrast, high-angle shot, low saturation, daylight, clean single shot, long-focus, fluorescent light, cool tones, center composition. In a dim environment, a Caucasian woman floats on her back in the water. In a top-down close-up shot, she has short brown hair and a few freckles on her face. As the camera pans down, she turns her head to the right, and ripples appear on the water's surface. The blurred background is pitch black, with only faint light illuminating the woman's face and a part of the water's surface, which appears blue. The woman is wearing a blue slip, her shoulders bare. | [videos/42.mp4](videos/42.mp4) |

---

## Summary of Formulas

| Formula | Structure | Use Case |
|---------|-----------|----------|
| **Basic** | Entity + Scene + Motion | Simple, flexible prompts for creative exploration |
| **Advanced** | Entity + Scene + Motion + Aesthetic control + Stylization | Rich, detailed descriptions for enhanced quality |
| **Image-to-Video** | Motion + Camera movement | When using an image as the starting frame |
| **Sound** | Entity + Scene + Motion + Sound description | Adding voice, SFX, and BGM to videos |
| **Reference-to-Video** | Character + Action + Lines + Scene | Referencing characters from existing videos |
| **Multi-shot** | Overall description + Shot number + Timestamp + Shot content | Multi-shot narrative videos with precise control |

---

*Note: For common cinematic terms, lighting terms, shot sizes, camera angles, lenses, camera movements, and visual styles, refer to the prompt dictionary (not shown in source images).*
