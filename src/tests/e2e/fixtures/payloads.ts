export interface EdgePayload {
  schema: string;
  version: string;
  data: Record<string, unknown>;
}

export const scriptWriterToSceneSplitterPayload: EdgePayload = {
  schema: 'script-output',
  version: '1.0.0',
  data: {
    script: {
      text: 'Welcome to the future! Today we are exploring the amazing world of AI assistants...',
      segments: [
        { start: 0, end: 5, text: 'Welcome to the future!' },
        { start: 5, end: 12, text: 'Today we are exploring the amazing world of AI assistants...' },
      ],
    },
    metadata: {
      tone: 'casual',
      duration: 30,
      wordCount: 250,
    },
  },
};

export const sceneSplitterToImageGeneratorPayload: EdgePayload = {
  schema: 'scenes-array',
  version: '1.0.0',
  data: {
    scenes: [
      {
        id: 'scene-1',
        prompt: 'Cinematic shot of a futuristic city at dusk, neon lights reflecting on wet streets, cyberpunk aesthetic',
        duration: 5,
        style: 'cinematic',
      },
      {
        id: 'scene-2',
        prompt: 'Close-up of an AI hologram interface floating in mid-air, blue glow, sci-fi aesthetic',
        duration: 4,
        style: 'futuristic',
      },
      {
        id: 'scene-3',
        prompt: 'Wide shot of a modern office with people interacting with AI assistants, warm lighting',
        duration: 6,
        style: 'natural',
      },
    ],
    totalDuration: 15,
  },
};

export const imageGeneratorToVideoComposerPayload: EdgePayload = {
  schema: 'generated-images',
  version: '1.0.0',
  data: {
    images: [
      {
        id: 'img-1',
        url: 'https://mock-cdn.example.com/generated/scene-1.png',
        width: 1920,
        height: 1080,
        prompt: 'Cinematic shot of a futuristic city at dusk',
        sceneId: 'scene-1',
      },
      {
        id: 'img-2',
        url: 'https://mock-cdn.example.com/generated/scene-2.png',
        width: 1920,
        height: 1080,
        prompt: 'Close-up of an AI hologram interface',
        sceneId: 'scene-2',
      },
      {
        id: 'img-3',
        url: 'https://mock-cdn.example.com/generated/scene-3.png',
        width: 1920,
        height: 1080,
        prompt: 'Wide shot of a modern office',
        sceneId: 'scene-3',
      },
    ],
    count: 3,
    format: 'png',
  },
};

export const videoComposerToFinalExportPayload: EdgePayload = {
  schema: 'composed-video',
  version: '1.0.0',
  data: {
    video: {
      url: 'https://mock-cdn.example.com/composed/final-video.mp4',
      duration: 15,
      format: 'mp4',
      resolution: { width: 1920, height: 1080 },
      fps: 30,
      fileSize: 15728640,
    },
    assets: {
      imageCount: 3,
      audioTrack: 'upbeat-corporate-music.mp3',
      transitions: ['fade', 'slide', 'fade'],
    },
  },
};

export const userPromptToScriptWriterPayload: EdgePayload = {
  schema: 'user-prompt',
  version: '1.0.0',
  data: {
    prompt: 'Create a viral tech review video about AI assistants',
    context: null,
    constraints: {
      maxDuration: 30,
      targetPlatform: 'tiktok',
    },
  },
};

export const ttsToFinalExportPayload: EdgePayload = {
  schema: 'tts-audio',
  version: '1.0.0',
  data: {
    audio: {
      url: 'https://mock-cdn.example.com/audio/narration.mp3',
      duration: 15.5,
      format: 'mp3',
      voice: 'alloy',
      speed: 1.0,
    },
    script: {
      text: 'Welcome to the future! Today we are exploring...',
      segments: [
        { start: 0, end: 5, text: 'Welcome to the future!' },
      ],
    },
  },
};
