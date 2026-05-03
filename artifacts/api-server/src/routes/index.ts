import { Router, type IRouter } from "express";
import healthRouter from "./health";
import dashboardRouter from "./dashboard";
import instrumentsRouter from "./instruments";
import batchesRouter from "./batches";
import sessionsRouter from "./sessions";
import fraudRouter from "./fraud";
import returnsRouter from "./returns";
import bcpRouter from "./bcp";
import auditRouter from "./audit";

const router: IRouter = Router();

router.use(healthRouter);
router.use(dashboardRouter);
router.use(instrumentsRouter);
router.use(batchesRouter);
router.use(sessionsRouter);
router.use(fraudRouter);
router.use(returnsRouter);
router.use(bcpRouter);
router.use(auditRouter);

export default router;
